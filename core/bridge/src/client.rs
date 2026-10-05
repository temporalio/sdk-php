use crate::config::{ClientJson, parse};
use crate::ffi::{KIND_RPC_RESULT, construct, free, guard, slice};
use crate::queue::Queue;
use crate::runtime::TpbRuntime;
use base64::{Engine, prelude::BASE64_STANDARD};
use prost::bytes::{Buf, BufMut};
use std::{
    collections::HashMap,
    future::Future,
    sync::{Arc, Mutex, PoisonError},
    time::Duration,
};
use temporalio_client::ClientKeepAliveOptions;
use tonic::{
    Code, Request, Status, TimeoutExpired,
    client::Grpc,
    codec::{Codec, DecodeBuf, Decoder, EncodeBuf, Encoder},
    codegen::http::uri::PathAndQuery,
    metadata::{AsciiMetadataKey, AsciiMetadataValue, BinaryMetadataKey, BinaryMetadataValue},
    transport::{Channel, Endpoint},
};

const MAX_GRPC_TIMEOUT: Duration = Duration::from_secs(99_999_999 * 60 * 60);

pub struct TpbClient {
    endpoint: Endpoint,
    channel: Arc<Mutex<Channel>>,
    queue: Arc<Queue>,
}

impl TpbClient {
    fn push(&self, tag: u64, (grpc_code, data): (i32, Vec<u8>)) {
        self.queue.push(tag, KIND_RPC_RESULT, grpc_code, data)
    }

    fn push_panic(&self, tag: u64) -> impl FnOnce(String) + '_ {
        move |message| self.push(tag, internal_error(message))
    }
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
    let keep_alive = ClientKeepAliveOptions::default();
    let endpoint = Endpoint::from_shared(config.target_url)
        .map_err(|e| e.to_string())?
        .connect_timeout(Duration::from_millis(config.connect_timeout_ms))
        .keep_alive_while_idle(true)
        .http2_keep_alive_interval(keep_alive.interval)
        .keep_alive_timeout(keep_alive.timeout);
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

fn timeout(timeout_ms: u64) -> Option<Duration> {
    (timeout_ms > 0).then(|| Duration::from_millis(timeout_ms).min(MAX_GRPC_TIMEOUT))
}

fn request(
    body: Vec<u8>,
    metadata: &[u8],
    timeout: Option<Duration>,
) -> Result<Request<Vec<u8>>, Status> {
    let mut request = Request::new(body);
    if let Some(timeout) = timeout {
        request.set_timeout(timeout);
    }
    if metadata.is_empty() {
        return Ok(request);
    }
    let metadata: HashMap<String, Vec<String>> =
        parse(metadata, "metadata").map_err(Status::invalid_argument)?;
    let invalid =
        |e: &dyn std::fmt::Display| Status::invalid_argument(format!("Invalid metadata: {e}"));
    for (key, values) in metadata {
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
    let result = match timeout {
        None => call.await,
        Some(timeout) => tokio::time::timeout(timeout, call)
            .await
            .unwrap_or_else(|_| Err(Status::deadline_exceeded("Deadline Exceeded"))),
    };
    result.map_err(client_status)
}

fn client_status(status: Status) -> Status {
    if timed_out(&status) {
        return Status::deadline_exceeded(status.message());
    }
    match (status.code(), std::error::Error::source(&status)) {
        (Code::Cancelled | Code::Unknown, Some(_)) => Status::unavailable(status.message()),
        _ => status,
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
    unsafe { construct(err, err_len, || new_client(&*rt, slice(config, config_len))) }
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
    let (c, path, body, metadata) = unsafe {
        (
            &*c,
            slice(path, path_len),
            slice(body, body_len),
            slice(metadata, metadata_len),
        )
    };
    let timeout = timeout(timeout_ms);
    guard(c.push_panic(tag), || {
        let prepared = PathAndQuery::try_from(path.to_vec())
            .map_err(|e| Status::invalid_argument(format!("Invalid RPC path: {e}")))
            .and_then(|path| Ok((path, request(body.to_vec(), metadata, timeout)?)));
        let (path, request) = match prepared {
            Ok(prepared) => prepared,
            Err(status) => return c.push(tag, grpc_result(Err(status))),
        };
        let channel = c
            .channel
            .lock()
            .unwrap_or_else(PoisonError::into_inner)
            .clone();
        let queue = c.queue.clone();
        c.queue
            .spawn(tag, KIND_RPC_RESULT, internal_error, async move {
                let (grpc_code, data) =
                    grpc_result(with_deadline(call(channel, path, request), timeout).await);
                queue.push(tag, KIND_RPC_RESULT, grpc_code, data);
            });
    })
}

#[unsafe(no_mangle)]
pub unsafe extern "C" fn tpb_client_connect(c: *mut TpbClient, tag: u64, timeout_ms: u64) {
    let c = unsafe { &*c };
    guard(c.push_panic(tag), || {
        let endpoint = c.endpoint.clone();
        let channel = c.channel.clone();
        let queue = c.queue.clone();
        c.queue
            .spawn(tag, KIND_RPC_RESULT, internal_error, async move {
                let connected = connect(endpoint, Duration::from_millis(timeout_ms)).await;
                let (grpc_code, data) = grpc_result(connected.map(|connected| {
                    *channel.lock().unwrap_or_else(PoisonError::into_inner) = connected;
                    Vec::new()
                }));
                queue.push(tag, KIND_RPC_RESULT, grpc_code, data);
            });
    })
}

#[unsafe(no_mangle)]
pub unsafe extern "C" fn tpb_client_free(c: *mut TpbClient) {
    unsafe { free(c) }
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::testing::{Event, events, grpc_server, runtime};

    const GET_SYSTEM_INFO: &[u8] =
        b"/temporal.api.workflowservice.v1.WorkflowService/GetSystemInfo";

    fn client(rt: *mut TpbRuntime, target_url: &str) -> *mut TpbClient {
        let config =
            format!(r#"{{"target_url":"{target_url}","tls":null,"connect_timeout_ms":1000}}"#);
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
        c
    }

    fn call(c: *mut TpbClient, tag: u64, path: &[u8], metadata: &[u8], timeout_ms: u64) {
        unsafe {
            tpb_client_call(
                c,
                tag,
                path.as_ptr().cast(),
                path.len(),
                std::ptr::null(),
                0,
                metadata.as_ptr().cast(),
                metadata.len(),
                timeout_ms,
            )
        }
    }

    fn message(data: &[u8]) -> &[u8] {
        let length = u32::from_le_bytes(data[..4].try_into().unwrap()) as usize;
        assert_eq!(data.len(), 4 + length);
        &data[4..]
    }

    #[test]
    fn call_and_connect_succeed_against_a_grpc_server() {
        let rt = runtime();
        let c = client(rt, &grpc_server());

        call(c, 1, GET_SYSTEM_INFO, b"", 0);
        call(c, 2, GET_SYSTEM_INFO, br#"{"client-name":["php"]}"#, 5_000);
        unsafe { tpb_client_connect(c, 3, 5_000) };

        let ok = Code::Ok as i32;
        assert_eq!(
            events(rt, 3),
            vec![
                (1, KIND_RPC_RESULT, ok, vec![]),
                (2, KIND_RPC_RESULT, ok, vec![]),
                (3, KIND_RPC_RESULT, ok, vec![]),
            ]
        );
        unsafe {
            tpb_client_free(c);
            free(rt);
        }
    }

    #[test]
    fn call_with_an_invalid_path_or_metadata_reports_invalid_argument() {
        let rt = runtime();
        let c = client(rt, "http://127.0.0.1:1");

        call(c, 7, b"no path", b"", 0);
        call(c, 8, GET_SYSTEM_INFO, br#"{"bad key":["x"]}"#, 0);

        let invalid = |(tag, kind, grpc_code, data): Event, expected_tag: u64, prefix: &[u8]| {
            let expected = (expected_tag, KIND_RPC_RESULT, Code::InvalidArgument as i32);
            assert_eq!((tag, kind, grpc_code), expected);
            assert!(message(&data).starts_with(prefix));
        };
        let [path, metadata] = events(rt, 2).try_into().unwrap();
        invalid(path, 7, b"Invalid RPC path: ");
        invalid(metadata, 8, b"Invalid metadata: ");
        unsafe {
            tpb_client_free(c);
            free(rt);
        }
    }

    #[test]
    fn a_poisoned_channel_lock_keeps_the_client_working() {
        let rt = runtime();
        let c = client(rt, &grpc_server());
        let channel = unsafe { &*c }.channel.clone();
        let poisoner = std::thread::spawn(move || {
            let _locked = channel.lock().unwrap();
            panic!("poisoned");
        });
        assert!(poisoner.join().is_err());

        call(c, 1, GET_SYSTEM_INFO, b"", 0);
        unsafe { tpb_client_connect(c, 2, 5_000) };
        call(c, 3, GET_SYSTEM_INFO, b"", 0);

        let ok = Code::Ok as i32;
        assert_eq!(
            events(rt, 3),
            vec![
                (1, KIND_RPC_RESULT, ok, vec![]),
                (2, KIND_RPC_RESULT, ok, vec![]),
                (3, KIND_RPC_RESULT, ok, vec![]),
            ]
        );
        unsafe {
            tpb_client_free(c);
            free(rt);
        }
    }

    #[test]
    fn a_panic_becomes_an_internal_result() {
        let rt = runtime();
        let c = client(rt, "http://127.0.0.1:1");

        guard(unsafe { &*c }.push_panic(4), || panic!("boom"));

        let [(tag, kind, grpc_code, data)] = events(rt, 1).try_into().unwrap();
        assert_eq!(
            (tag, kind, grpc_code),
            (4, KIND_RPC_RESULT, Code::Internal as i32)
        );
        assert_eq!(message(&data), b"Panic in temporal-php-bridge: boom");
        unsafe {
            tpb_client_free(c);
            free(rt);
        }
    }

    #[test]
    fn endpoint_reads_tls_and_rejects_an_invalid_url() {
        let endpoint_of = |target_url: &str, tls: &str| {
            let json =
                format!(r#"{{"target_url":"{target_url}","tls":{tls},"connect_timeout_ms":1}}"#);
            endpoint(parse(json.as_bytes(), "client config").unwrap())
        };
        let tls = |domain: &str| {
            format!(
                r#"{{"server_root_ca_cert":null,"domain":{domain},"client_cert":null,"client_private_key":null}}"#
            )
        };

        assert!(endpoint_of("https://127.0.0.1:7233", &tls(r#""example.com""#)).is_ok());
        assert!(endpoint_of("https://127.0.0.1:7233", &tls("null")).is_ok());
        assert!(endpoint_of("not a url", "null").is_err());
    }

    #[test]
    fn request_reads_ascii_and_base64_binary_metadata() {
        let metadata = br#"{"Client-Name":["php"],"trace-bin":["AAEC"]}"#;
        let prepared = request(vec![1], metadata, timeout(1500)).unwrap();
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
        assert!(request(vec![], br#"{"bad key":["x"]}"#, None).is_err());
        assert!(request(vec![], br#"{"key":"x"}"#, None).is_err());
        assert!(request(vec![], br#"{"key":[1]}"#, None).is_err());
    }

    #[test]
    fn timeout_is_capped_to_the_longest_grpc_timeout() {
        assert_eq!(timeout(0), None);
        assert_eq!(timeout(1500), Some(Duration::from_millis(1500)));
        assert_eq!(timeout(u64::MAX), Some(MAX_GRPC_TIMEOUT));
        let capped = request(vec![], b"", timeout(u64::MAX)).unwrap();
        assert_eq!(capped.metadata().get("grpc-timeout").unwrap(), "99999999H");
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
        let expired_status = Status::from_error(Box::new(TimeoutExpired(())));
        let transport_error = std::io::Error::other(expired_status);
        let expired = rt.block_on(with_deadline(
            async { Err(Status::from_error(Box::new(transport_error))) },
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
    fn transport_failures_are_unavailable_and_server_statuses_are_kept() {
        let transport =
            |error: std::io::Error| client_status(Status::from_error(Box::new(error))).code();
        let reset = std::io::Error::other("connection reset");
        assert_eq!(transport(reset), Code::Unavailable);
        let canceled = std::io::Error::other(Status::cancelled("operation was canceled"));
        assert_eq!(transport(canceled), Code::Unavailable);
        for code in [Code::Cancelled, Code::Unknown, Code::Internal] {
            assert_eq!(client_status(Status::new(code, "server")).code(), code);
        }
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
