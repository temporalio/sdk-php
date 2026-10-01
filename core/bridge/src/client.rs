use crate::config::{ClientJson, parse};
use crate::ffi::{KIND_RPC_RESULT, free, guard, into_ffi, slice};
use crate::queue::Queue;
use crate::runtime::TpbRuntime;
use base64::{Engine, prelude::BASE64_STANDARD};
use prost::bytes::{Buf, BufMut};
use std::{
    collections::HashMap,
    future::Future,
    sync::{Arc, Mutex},
    time::Duration,
};
use tonic::{
    Code, Request, Status, TimeoutExpired,
    client::Grpc,
    codec::{Codec, DecodeBuf, Decoder, EncodeBuf, Encoder},
    codegen::http::uri::PathAndQuery,
    metadata::{AsciiMetadataKey, AsciiMetadataValue, BinaryMetadataKey, BinaryMetadataValue},
    transport::{Channel, Endpoint},
};

pub struct TpbClient {
    endpoint: Endpoint,
    channel: Arc<Mutex<Channel>>,
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

fn endpoint(config: ClientJson) -> Result<Endpoint, String> {
    let endpoint = Endpoint::from_shared(config.target_url).map_err(|e| e.to_string())?;
    let Some(tls) = config.tls else {
        return Ok(endpoint);
    };
    let (tls, origin) = tls.client_config()?;
    let endpoint = match origin {
        Some(origin) => endpoint.origin(origin),
        None => endpoint,
    };
    endpoint.tls_config(tls).map_err(|e| e.to_string())
}

fn new_client(rt: &TpbRuntime, config: &[u8]) -> Result<TpbClient, String> {
    let endpoint = endpoint(parse(config, "client config")?)?;
    let channel = {
        let _guard = rt.queue.handle.enter();
        endpoint.connect_lazy()
    };
    Ok(TpbClient {
        endpoint,
        channel: Arc::new(Mutex::new(channel)),
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
    let metadata: HashMap<String, Vec<String>> =
        parse(metadata, "metadata").map_err(Status::invalid_argument)?;
    let invalid =
        |e: &dyn std::fmt::Display| Status::invalid_argument(format!("Invalid metadata: {e}"));
    for (key, values) in metadata {
        let key = key.to_ascii_lowercase();
        for value in values {
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

async fn connect(endpoint: Endpoint, timeout: Duration) -> Result<Channel, Status> {
    match tokio::time::timeout(timeout, endpoint.connect()).await {
        Err(_) => Err(Status::deadline_exceeded("Connection timeout expired")),
        Ok(result) => result.map_err(|e| Status::from_error(e.into())),
    }
}

async fn with_deadline(
    call: impl Future<Output = Result<Vec<u8>, Status>>,
    timeout: Option<Duration>,
) -> Result<Vec<u8>, Status> {
    let Some(timeout) = timeout else {
        return call.await;
    };
    match tokio::time::timeout(timeout, call).await {
        Err(_) => Err(Status::deadline_exceeded("Deadline Exceeded")),
        Ok(Err(status)) if status.code() == Code::Cancelled && timed_out(&status) => {
            Err(Status::deadline_exceeded(status.message()))
        }
        Ok(result) => result,
    }
}

fn timed_out(status: &Status) -> bool {
    let mut source = std::error::Error::source(status);
    while let Some(error) = source {
        if error.is::<TimeoutExpired>() {
            return true;
        }
        source = error.source();
    }
    false
}

fn status_bytes(status: &Status) -> Vec<u8> {
    let message = status.message().as_bytes();
    let mut bytes = Vec::with_capacity(4 + message.len() + status.details().len());
    bytes.extend_from_slice(&(message.len() as u32).to_le_bytes());
    bytes.extend_from_slice(message);
    bytes.extend_from_slice(status.details());
    bytes
}

fn grpc_result(result: Result<Vec<u8>, Status>) -> (i32, Vec<u8>) {
    match result {
        Ok(bytes) => (Code::Ok as i32, bytes),
        Err(status) => (status.code() as i32, status_bytes(&status)),
    }
}

fn internal_error(message: String) -> (i32, Vec<u8>) {
    grpc_result(Err(Status::internal(message)))
}

#[unsafe(no_mangle)]
pub unsafe extern "C" fn tpb_client_new(
    rt: *mut TpbRuntime,
    config: *const libc::c_char,
    config_len: usize,
    err: *mut *mut u8,
    err_len: *mut usize,
) -> *mut TpbClient {
    guard(
        |message| unsafe { into_ffi(Err(message), err, err_len) },
        || unsafe { into_ffi(new_client(&*rt, slice(config, config_len)), err, err_len) },
    )
}

#[unsafe(no_mangle)]
pub unsafe extern "C" fn tpb_client_call(
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
    let push =
        |(grpc_code, data): (i32, Vec<u8>)| c.queue.push(tag, KIND_RPC_RESULT, grpc_code, data);
    guard(
        |message| push(internal_error(message)),
        || {
            let prepared = PathAndQuery::try_from(unsafe { slice(path, path_len) }.to_vec())
                .map_err(|e| Status::invalid_argument(format!("Invalid RPC path: {e}")))
                .and_then(|path| {
                    let request = request(
                        unsafe { slice(body, body_len) }.to_vec(),
                        unsafe { slice(metadata, metadata_len) },
                        timeout_ms,
                    )?;
                    Ok((path, request))
                });
            let (path, request) = match prepared {
                Ok(prepared) => prepared,
                Err(status) => return push(grpc_result(Err(status))),
            };
            let channel = c.channel.lock().unwrap().clone();
            let queue = c.queue.clone();
            c.queue
                .spawn(tag, KIND_RPC_RESULT, internal_error, async move {
                    let timeout = (timeout_ms > 0).then(|| Duration::from_millis(timeout_ms));
                    let (grpc_code, data) =
                        grpc_result(with_deadline(call(channel, path, request), timeout).await);
                    queue.push(tag, KIND_RPC_RESULT, grpc_code, data);
                });
        },
    )
}

#[unsafe(no_mangle)]
pub unsafe extern "C" fn tpb_client_connect(c: *mut TpbClient, tag: u64, timeout_ms: u64) {
    let c = unsafe { &*c };
    guard(
        |message| {
            let (grpc_code, data) = internal_error(message);
            c.queue.push(tag, KIND_RPC_RESULT, grpc_code, data)
        },
        || {
            let endpoint = c.endpoint.clone();
            let channel = c.channel.clone();
            let queue = c.queue.clone();
            c.queue
                .spawn(tag, KIND_RPC_RESULT, internal_error, async move {
                    let connected = connect(endpoint, Duration::from_millis(timeout_ms)).await;
                    let (grpc_code, data) = grpc_result(connected.map(|connected| {
                        *channel.lock().unwrap() = connected;
                        Vec::new()
                    }));
                    queue.push(tag, KIND_RPC_RESULT, grpc_code, data);
                });
        },
    )
}

#[unsafe(no_mangle)]
pub unsafe extern "C" fn tpb_client_free(c: *mut TpbClient) {
    unsafe { free(c) }
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::testing::{events, runtime};

    #[test]
    fn call_with_an_invalid_path_reports_invalid_argument() {
        let rt = runtime();
        let config = br#"{"target_url":"http://127.0.0.1:1","tls":null}"#;
        let c = unsafe {
            tpb_client_new(
                rt,
                config.as_ptr().cast(),
                config.len(),
                std::ptr::null_mut(),
                std::ptr::null_mut(),
            )
        };
        assert!(!c.is_null());
        let path = b"no path";
        unsafe {
            tpb_client_call(
                c,
                7,
                path.as_ptr().cast(),
                path.len(),
                std::ptr::null(),
                0,
                std::ptr::null(),
                0,
                0,
            )
        };
        let [(7, KIND_RPC_RESULT, grpc_code, ref data)] = events(rt, 1)[..] else {
            panic!("one RPC result expected");
        };
        assert_eq!(grpc_code, Code::InvalidArgument as i32);
        let length = u32::from_le_bytes(data[..4].try_into().unwrap()) as usize;
        assert_eq!(data.len(), 4 + length);
        assert!(data[4..].starts_with(b"Invalid RPC path: "));
        unsafe {
            tpb_client_free(c);
            free(rt);
        }
    }

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
        assert!(request(vec![], br#"{"key":"x"}"#, 0).is_err());
        assert!(request(vec![], br#"{"key":[1]}"#, 0).is_err());
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
        let expired = rt.block_on(with_deadline(
            async { Err(Status::from_error(Box::new(TimeoutExpired(())))) },
            timeout,
        ));
        assert_eq!(expired.unwrap_err().code(), Code::DeadlineExceeded);
        let cancelled = rt.block_on(with_deadline(
            async { Err(Status::cancelled("cancelled by the server")) },
            timeout,
        ));
        assert_eq!(cancelled.unwrap_err().code(), Code::Cancelled);
    }

    #[test]
    fn connect_reports_unavailable_and_timeout() {
        let rt = tokio::runtime::Builder::new_current_thread()
            .enable_all()
            .build()
            .unwrap();
        let closed = Endpoint::from_static("http://127.0.0.1:1");
        let refused = rt.block_on(connect(closed, Duration::from_secs(5)));
        assert_eq!(refused.unwrap_err().code(), Code::Unavailable);
        let unreachable = Endpoint::from_static("http://192.0.2.1:7233");
        let timeout = rt.block_on(connect(unreachable, Duration::ZERO));
        assert_eq!(timeout.unwrap_err().code(), Code::DeadlineExceeded);
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
