use crate::{KIND_RPC_RESULT, Queue, TpbRuntime, guard, into_raw_bytes, slice};
use base64::{Engine, prelude::BASE64_STANDARD};
use prost::bytes::{Buf, BufMut};
use serde_json::Value;
use std::{
    future::Future,
    sync::Arc,
    time::{Duration, Instant},
};
use tokio::runtime::Handle;
use tonic::{
    Code, Request, Status,
    client::Grpc,
    codec::{Codec, DecodeBuf, Decoder, EncodeBuf, Encoder},
    codegen::http::uri::{PathAndQuery, Uri},
    metadata::{AsciiMetadataKey, AsciiMetadataValue, BinaryMetadataKey, BinaryMetadataValue},
    transport::{Certificate, Channel, ClientTlsConfig, Endpoint, Identity},
};

pub struct TpbClient {
    channel: Channel,
    handle: Handle,
    queue: Arc<Queue>,
}

#[derive(Clone, Copy)]
struct RawCodec;

impl Codec for RawCodec {
    type Encode = Vec<u8>;
    type Decode = Vec<u8>;
    type Encoder = RawCodec;
    type Decoder = RawCodec;

    fn encoder(&mut self) -> Self::Encoder {
        RawCodec
    }

    fn decoder(&mut self) -> Self::Decoder {
        RawCodec
    }
}

impl Encoder for RawCodec {
    type Item = Vec<u8>;
    type Error = Status;

    fn encode(&mut self, item: Vec<u8>, dst: &mut EncodeBuf<'_>) -> Result<(), Status> {
        dst.put_slice(&item);
        Ok(())
    }
}

impl Decoder for RawCodec {
    type Item = Vec<u8>;
    type Error = Status;

    fn decode(&mut self, src: &mut DecodeBuf<'_>) -> Result<Option<Vec<u8>>, Status> {
        let mut bytes = vec![0; src.remaining()];
        src.copy_to_slice(&mut bytes);
        Ok(Some(bytes))
    }
}

fn endpoint(config: &Value) -> Result<Endpoint, String> {
    let target = config
        .get("target_url")
        .and_then(Value::as_str)
        .unwrap_or("http://127.0.0.1:7233");
    let mut endpoint = Endpoint::from_shared(target.to_owned()).map_err(|e| e.to_string())?;
    let Some(tls) = config.get("tls").and_then(Value::as_object) else {
        return Ok(endpoint);
    };
    let text = |key: &str| tls.get(key).and_then(Value::as_str);
    let mut tls_config = match text("server_root_ca_cert") {
        Some(ca) => ClientTlsConfig::new().ca_certificate(Certificate::from_pem(ca)),
        None => ClientTlsConfig::new().with_native_roots(),
    };
    if let Some(domain) = text("domain") {
        tls_config = tls_config.domain_name(domain);
        let origin: Uri = format!("https://{domain}")
            .parse()
            .map_err(|e| format!("{e}"))?;
        endpoint = endpoint.origin(origin);
    }
    if let (Some(cert), Some(key)) = (text("client_cert"), text("client_private_key")) {
        tls_config = tls_config.identity(Identity::from_pem(cert, key));
    }
    endpoint.tls_config(tls_config).map_err(|e| e.to_string())
}

fn new_client(rt: &TpbRuntime, config: &[u8]) -> Result<TpbClient, String> {
    let config: Value =
        serde_json::from_slice(config).map_err(|e| format!("Invalid client config JSON: {e}"))?;
    let handle = rt.core.tokio_handle();
    let channel = {
        let _guard = handle.enter();
        endpoint(&config)?.connect_lazy()
    };
    Ok(TpbClient {
        channel,
        handle,
        queue: rt.queue.clone(),
    })
}

fn request(body: Vec<u8>, metadata: &[u8], timeout_ms: u64) -> Result<Request<Vec<u8>>, Status> {
    let mut request = Request::new(body);
    if timeout_ms > 0 {
        request.set_timeout(Duration::from_millis(timeout_ms));
    }
    if metadata.is_empty() {
        return Ok(request);
    }
    let metadata: serde_json::Map<String, Value> = serde_json::from_slice(metadata)
        .map_err(|e| Status::invalid_argument(format!("Invalid metadata JSON: {e}")))?;
    let invalid =
        |e: &dyn std::fmt::Display| Status::invalid_argument(format!("Invalid metadata: {e}"));
    for (key, values) in metadata {
        let key = key.to_ascii_lowercase();
        for value in values
            .as_array()
            .into_iter()
            .flatten()
            .filter_map(Value::as_str)
        {
            if key.ends_with("-bin") {
                let name =
                    BinaryMetadataKey::from_bytes(key.as_bytes()).map_err(|e| invalid(&e))?;
                let value = BASE64_STANDARD.decode(value).map_err(|e| invalid(&e))?;
                request
                    .metadata_mut()
                    .append_bin(name, BinaryMetadataValue::from_bytes(&value));
            } else {
                let name = AsciiMetadataKey::from_bytes(key.as_bytes()).map_err(|e| invalid(&e))?;
                let value = AsciiMetadataValue::try_from(value).map_err(|e| invalid(&e))?;
                request.metadata_mut().append(name, value);
            }
        }
    }
    Ok(request)
}

async fn call(
    channel: Channel,
    path: PathAndQuery,
    request: Request<Vec<u8>>,
) -> Result<Vec<u8>, Status> {
    let mut grpc = Grpc::new(channel);
    grpc.ready()
        .await
        .map_err(|e| Status::unavailable(e.to_string()))?;
    Ok(grpc.unary(request, path, RawCodec).await?.into_inner())
}

async fn with_deadline(
    call: impl Future<Output = Result<Vec<u8>, Status>>,
    timeout: Option<Duration>,
) -> Result<Vec<u8>, Status> {
    let Some(timeout) = timeout else {
        return call.await;
    };
    let started = Instant::now();
    match tokio::time::timeout(timeout, call).await {
        Err(_) => Err(Status::deadline_exceeded("Deadline Exceeded")),
        Ok(Err(status)) if status.code() == Code::Cancelled && started.elapsed() >= timeout => {
            Err(Status::deadline_exceeded(status.message()))
        }
        Ok(result) => result,
    }
}

fn status_bytes(status: &Status) -> Vec<u8> {
    let message = status.message().as_bytes();
    let mut bytes = Vec::with_capacity(4 + message.len() + status.details().len());
    bytes.extend_from_slice(&(message.len() as u32).to_le_bytes());
    bytes.extend_from_slice(message);
    bytes.extend_from_slice(status.details());
    bytes
}

#[unsafe(no_mangle)]
pub extern "C" fn tpb_client_new(
    rt: *mut TpbRuntime,
    config: *const libc::c_char,
    config_len: usize,
    err: *mut *mut u8,
    err_len: *mut usize,
) -> *mut TpbClient {
    match guard(Err, || {
        new_client(unsafe { &*rt }, slice(config, config_len))
    }) {
        Ok(client) => Box::into_raw(Box::new(client)),
        Err(message) => {
            let (data, len) = into_raw_bytes(message.into_bytes());
            unsafe {
                if !err.is_null() {
                    *err = data;
                }
                if !err_len.is_null() {
                    *err_len = len;
                }
            }
            std::ptr::null_mut()
        }
    }
}

#[unsafe(no_mangle)]
pub extern "C" fn tpb_client_call(
    c: *mut TpbClient,
    tag: u64,
    path: *const libc::c_char,
    path_len: usize,
    body: *const libc::c_char,
    body_len: usize,
    metadata: *const libc::c_char,
    metadata_len: usize,
    timeout_ms: u64,
) {
    let c = unsafe { &*c };
    let queue = c.queue.clone();
    let prepared = guard(
        |message| Err(Status::internal(message)),
        || {
            let path = PathAndQuery::try_from(slice(path, path_len).to_vec())
                .map_err(|e| Status::invalid_argument(format!("Invalid RPC path: {e}")))?;
            let request = request(
                slice(body, body_len).to_vec(),
                slice(metadata, metadata_len),
                timeout_ms,
            )?;
            Ok((path, request))
        },
    );
    let (path, request) = match prepared {
        Ok(prepared) => prepared,
        Err(status) => {
            return queue.push(
                tag,
                KIND_RPC_RESULT,
                status.code() as i32,
                status_bytes(&status),
            );
        }
    };
    let channel = c.channel.clone();
    c.handle.spawn(async move {
        let timeout = (timeout_ms > 0).then(|| Duration::from_millis(timeout_ms));
        match with_deadline(call(channel, path, request), timeout).await {
            Ok(bytes) => queue.push(tag, KIND_RPC_RESULT, Code::Ok as i32, bytes),
            Err(status) => queue.push(
                tag,
                KIND_RPC_RESULT,
                status.code() as i32,
                status_bytes(&status),
            ),
        }
    });
}

#[unsafe(no_mangle)]
pub extern "C" fn tpb_client_free(c: *mut TpbClient) {
    if !c.is_null() {
        guard(|_| (), || drop(unsafe { Box::from_raw(c) }));
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn request_reads_ascii_and_base64_binary_metadata() {
        let metadata = br#"{"Client-Name":["php"],"trace-bin":["AAEC"]}"#;
        let prepared = request(vec![1], metadata, 1500).unwrap();
        assert_eq!(prepared.metadata().get("client-name").unwrap(), "php");
        assert_eq!(
            prepared
                .metadata()
                .get_bin("trace-bin")
                .unwrap()
                .to_bytes()
                .unwrap()
                .as_ref(),
            &[0, 1, 2]
        );
        assert!(request(vec![], br#"{"bad key":["x"]}"#, 0).is_err());
    }

    #[test]
    fn expired_deadline_is_deadline_exceeded() {
        let rt = tokio::runtime::Builder::new_current_thread()
            .enable_time()
            .build()
            .unwrap();
        let timeout = Some(Duration::from_millis(10));
        let pending = rt.block_on(with_deadline(std::future::pending(), timeout));
        assert_eq!(pending.unwrap_err().code(), Code::DeadlineExceeded);
        let cancelled = rt.block_on(with_deadline(
            async {
                tokio::time::sleep(Duration::from_millis(20)).await;
                Err(Status::cancelled("Timeout expired"))
            },
            Some(Duration::from_millis(30)),
        ));
        assert_eq!(cancelled.unwrap_err().code(), Code::Cancelled);
        let late = rt.block_on(with_deadline(
            async {
                tokio::time::sleep(Duration::from_millis(20)).await;
                Err(Status::cancelled("Timeout expired"))
            },
            Some(Duration::from_millis(20)),
        ));
        assert!(matches!(late.unwrap_err().code(), Code::DeadlineExceeded));
    }

    #[test]
    fn status_bytes_prefix_the_message_length() {
        let status = Status::with_details(Code::NotFound, "gone", vec![9, 9].into());
        assert_eq!(
            status_bytes(&status),
            [4, 0, 0, 0, b'g', b'o', b'n', b'e', 9, 9]
        );
    }
}
