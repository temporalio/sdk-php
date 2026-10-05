use crate::config::{ClientJson, parse};
use crate::ffi::{KIND_RPC_RESULT, bytes, construct, guard, object, release, required};
use crate::queue::Queue;
use crate::runtime::TpbRuntime;
use base64::{Engine, prelude::BASE64_STANDARD};
use hyper_util::rt::TokioIo;
use prost::bytes::{Buf, BufMut};
use std::{
    collections::HashMap,
    future::Future,
    io,
    sync::{Arc, Mutex, PoisonError},
    time::Duration,
};
use temporalio_client::ClientKeepAliveOptions;
use tokio::{net::UnixStream, sync::watch};
use tonic::{
    Code, Request, Status, TimeoutExpired,
    client::Grpc,
    codec::{Codec, DecodeBuf, Decoder, EncodeBuf, Encoder},
    codegen::http::uri::PathAndQuery,
    metadata::{AsciiMetadataKey, AsciiMetadataValue, BinaryMetadataKey, BinaryMetadataValue},
    transport::{Channel, Endpoint, Uri},
};
use tower::{Service, service_fn};

const MAX_GRPC_TIMEOUT: Duration = Duration::from_secs(99_999_999 * 60 * 60);
const CHANNEL_CLOSED: &str = "Channel is closed";

pub struct TpbClient {
    target: Target,
    channel: Arc<Mutex<Channel>>,
    queue: Arc<Queue>,
    open: watch::Sender<()>,
}

impl TpbClient {
    fn push(&self, tag: u64, (grpc_code, data): (i32, Vec<u8>)) {
        self.queue.push(tag, KIND_RPC_RESULT, grpc_code, data)
    }

    fn push_panic(&self, tag: u64) -> impl FnOnce(String) + '_ {
        move |message| self.push(tag, internal_error(message))
    }

    fn spawn(
        &self,
        tag: u64,
        task: impl Future<Output = Result<Vec<u8>, Status>> + Send + 'static,
    ) {
        let queue = self.queue.clone();
        let mut open = self.open.subscribe();
        self.queue
            .spawn(tag, KIND_RPC_RESULT, internal_error, async move {
                let result = tokio::select! {
                    result = task => result,
                    _ = open.changed() => Err(Status::cancelled(CHANNEL_CLOSED)),
                };
                let (grpc_code, data) = grpc_result(result);
                queue.push(tag, KIND_RPC_RESULT, grpc_code, data);
            });
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

#[derive(Clone)]
struct Target {
    endpoint: Endpoint,
    socket: Option<Arc<str>>,
}

impl Target {
    fn connect_lazy(&self) -> Channel {
        match &self.socket {
            None => self.endpoint.connect_lazy(),
            Some(path) => self
                .endpoint
                .connect_with_connector_lazy(unix_socket(path.clone())),
        }
    }

    async fn connect(self) -> Result<Channel, tonic::transport::Error> {
        match self.socket {
            None => self.endpoint.connect().await,
            Some(path) => {
                self.endpoint
                    .connect_with_connector(unix_socket(path))
                    .await
            }
        }
    }
}

fn unix_socket(
    path: Arc<str>,
) -> impl Service<
    Uri,
    Response = TokioIo<UnixStream>,
    Error = io::Error,
    Future = impl Future<Output = io::Result<TokioIo<UnixStream>>> + Send,
> + Send
+ 'static {
    service_fn(move |_: Uri| {
        let path = path.clone();
        async move { UnixStream::connect(&*path).await.map(TokioIo::new) }
    })
}

fn grpc_target(target_url: &str) -> (String, Option<Arc<str>>) {
    let Some((scheme, target)) = target_url.split_once("://") else {
        return (target_url.to_owned(), None);
    };
    if let Some(path) = target.strip_prefix("unix:") {
        let path = path.strip_prefix("//").unwrap_or(path);
        return (format!("{scheme}://localhost"), Some(path.into()));
    }
    let authority = match target.split_once(':') {
        Some(("dns", name)) => name.strip_prefix("//").map_or(name, |name| {
            name.split_once('/').map_or(name, |(_, name)| name)
        }),
        Some(("ipv4" | "ipv6", address)) => address,
        _ => target,
    };
    (format!("{scheme}://{authority}"), None)
}

fn target(config: ClientJson) -> Result<Target, String> {
    let (uri, socket) = grpc_target(&config.target_url);
    let keep_alive = ClientKeepAliveOptions::default();
    let endpoint = Endpoint::from_shared(uri)
        .map_err(|e| e.to_string())?
        .connect_timeout(Duration::from_millis(config.connect_timeout_ms))
        .keep_alive_while_idle(true)
        .http2_keep_alive_interval(keep_alive.interval)
        .keep_alive_timeout(keep_alive.timeout);
    let Some(tls) = config.tls else {
        return Ok(Target { endpoint, socket });
    };
    let (tls, origin) = tls.client_config()?;
    let endpoint = match origin {
        Some(origin) => endpoint.origin(origin),
        None => endpoint,
    };
    let endpoint = endpoint.tls_config(tls).map_err(|e| e.to_string())?;
    Ok(Target { endpoint, socket })
}

fn new_client(rt: &TpbRuntime, config: &[u8]) -> Result<TpbClient, String> {
    let target = target(parse(config, "client config")?)?;
    let channel = {
        let _guard = rt.queue.handle.enter();
        target.connect_lazy()
    };
    Ok(TpbClient {
        target,
        channel: Arc::new(Mutex::new(channel)),
        queue: rt.queue.clone(),
        open: watch::Sender::new(()),
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

async fn connect(target: Target, timeout: Duration) -> Result<Channel, Status> {
    match tokio::time::timeout(timeout, target.connect()).await {
        Err(_) => Err(Status::deadline_exceeded("Connection timeout expired")),
        Ok(result) => result.map_err(|e| Status::from_error(e.into())),
    }
}

const DEADLINE_EXCEEDED: &str = "Deadline Exceeded";

async fn with_deadline(
    call: impl Future<Output = Result<Vec<u8>, Status>>,
    timeout: Option<Duration>,
) -> Result<Vec<u8>, Status> {
    let result = match timeout {
        None => call.await,
        Some(timeout) => tokio::time::timeout(timeout, call)
            .await
            .unwrap_or_else(|_| Err(Status::deadline_exceeded(DEADLINE_EXCEEDED))),
    };
    result.map_err(client_status)
}

fn client_status(status: Status) -> Status {
    if timed_out(&status) {
        return Status::deadline_exceeded(DEADLINE_EXCEEDED);
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
    construct(err, err_len, || {
        new_client(required(rt, "runtime")?, bytes(config, config_len))
    })
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
    let Some(c) = object(c) else {
        return;
    };
    let (path, body, metadata) = (
        bytes(path, path_len),
        bytes(body, body_len),
        bytes(metadata, metadata_len),
    );
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
        c.spawn(tag, with_deadline(call(channel, path, request), timeout));
    })
}

#[unsafe(no_mangle)]
pub unsafe extern "C" fn tpb_client_connect(c: *mut TpbClient, tag: u64, timeout_ms: u64) {
    let Some(c) = object(c) else {
        return;
    };
    guard(c.push_panic(tag), || {
        let target = c.target.clone();
        let channel = c.channel.clone();
        c.spawn(tag, async move {
            let connected = connect(target, Duration::from_millis(timeout_ms)).await?;
            *channel.lock().unwrap_or_else(PoisonError::into_inner) = connected;
            Ok(Vec::new())
        });
    })
}

#[unsafe(no_mangle)]
pub unsafe extern "C" fn tpb_client_free(c: *mut TpbClient) {
    release(c)
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::ffi::{bytes, tpb_bytes_free};
    use crate::testing::{Checked, Event, events, grpc_server, only, runtime};

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
        let mut length = [0; 4];
        length.copy_from_slice(&data[..4]);
        let length = u32::from_le_bytes(length) as usize;
        assert_eq!(data.len(), 4 + length);
        &data[4..]
    }

    #[test]
    fn call_and_connect_succeed_against_a_grpc_server() -> Checked {
        let rt = runtime();
        let c = client(rt, &grpc_server()?);

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
        unsafe { tpb_client_free(c) };
        release(rt);
        Ok(())
    }

    fn unix_forwarder(socket: &std::path::Path, address: String) -> std::io::Result<()> {
        let _ = std::fs::remove_file(socket);
        let listener = std::os::unix::net::UnixListener::bind(socket)?;
        listener.set_nonblocking(true)?;
        std::thread::spawn(move || -> std::io::Result<()> {
            let rt = tokio::runtime::Builder::new_current_thread()
                .enable_all()
                .build()?;
            rt.block_on(async move {
                let listener = tokio::net::UnixListener::from_std(listener)?;
                loop {
                    let (mut unix, _) = listener.accept().await?;
                    let mut tcp = tokio::net::TcpStream::connect(&address).await?;
                    tokio::spawn(async move {
                        tokio::io::copy_bidirectional(&mut unix, &mut tcp).await
                    });
                }
            })
        });
        Ok(())
    }

    #[test]
    fn calls_reach_the_server_through_dns_ipv4_and_unix_targets() -> Checked {
        let rt = runtime();
        let server = grpc_server()?;
        let address = server.trim_start_matches("http://");
        let socket = std::env::temp_dir().join(format!("tpb-{}.sock", std::process::id()));
        unix_forwarder(&socket, address.to_owned())?;
        let socket = socket.display();
        let clients = [
            format!("http://dns:///{address}"),
            format!("http://dns:{address}"),
            format!("http://dns://127.0.0.53/{address}"),
            format!("http://ipv4:{address}"),
            format!("http://unix:{socket}"),
            format!("http://unix://{socket}"),
        ]
        .map(|target| client(rt, &target));

        for (tag, c) in (1..).zip(clients) {
            call(c, tag, GET_SYSTEM_INFO, b"", 5_000);
        }
        unsafe { tpb_client_connect(clients[4], 7, 5_000) };

        let ok = |tag| (tag, KIND_RPC_RESULT, Code::Ok as i32, vec![]);
        assert_eq!(events(rt, 7), (1..=7).map(ok).collect::<Vec<_>>());
        for c in clients {
            unsafe { tpb_client_free(c) };
        }
        release(rt);
        Ok(())
    }

    #[test]
    fn freeing_the_client_cancels_its_calls_in_flight() -> Checked {
        let rt = runtime();
        let silent = std::net::TcpListener::bind("127.0.0.1:0")?;
        let c = client(rt, &format!("http://{}", silent.local_addr()?));

        call(c, 1, GET_SYSTEM_INFO, b"", 60_000);
        std::thread::sleep(Duration::from_millis(300));
        unsafe { tpb_client_free(c) };

        let [(tag, kind, grpc_code, data)] = only(events(rt, 1))?;
        assert_eq!(
            (tag, kind, grpc_code),
            (1, KIND_RPC_RESULT, Code::Cancelled as i32)
        );
        assert_eq!(message(&data), CHANNEL_CLOSED.as_bytes());
        release(rt);
        Ok(())
    }

    #[test]
    fn call_with_an_invalid_path_or_metadata_reports_invalid_argument() -> Checked {
        let rt = runtime();
        let c = client(rt, "http://127.0.0.1:1");

        call(c, 7, b"no path", b"", 0);
        call(c, 8, GET_SYSTEM_INFO, br#"{"bad key":["x"]}"#, 0);

        let invalid = |(tag, kind, grpc_code, data): Event, expected_tag: u64, prefix: &[u8]| {
            let expected = (expected_tag, KIND_RPC_RESULT, Code::InvalidArgument as i32);
            assert_eq!((tag, kind, grpc_code), expected);
            assert!(message(&data).starts_with(prefix));
        };
        let [path, metadata] = only(events(rt, 2))?;
        invalid(path, 7, b"Invalid RPC path: ");
        invalid(metadata, 8, b"Invalid metadata: ");
        unsafe { tpb_client_free(c) };
        release(rt);
        Ok(())
    }

    #[test]
    fn a_poisoned_channel_lock_keeps_the_client_working() -> Checked {
        let rt = runtime();
        let c = client(rt, &grpc_server()?);
        let channel = unsafe { &*c }.channel.clone();
        let poisoner = std::thread::spawn(move || {
            let _locked = channel.lock();
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
        unsafe { tpb_client_free(c) };
        release(rt);
        Ok(())
    }

    #[test]
    fn null_runtime_and_client_pointers_do_not_crash() {
        let (mut err, mut err_len) = (std::ptr::null_mut(), 0);
        let config = br#"{"target_url":"http://127.0.0.1:1","tls":null,"connect_timeout_ms":1}"#;
        let c = unsafe {
            tpb_client_new(
                std::ptr::null_mut(),
                config.as_ptr().cast(),
                config.len(),
                &mut err,
                &mut err_len,
            )
        };
        assert!(c.is_null());
        assert_eq!(bytes(err.cast(), err_len), b"The runtime pointer is null");
        unsafe { tpb_bytes_free(err, err_len) };

        call(c, 1, GET_SYSTEM_INFO, b"", 0);
        unsafe {
            tpb_client_connect(c, 2, 0);
            tpb_client_free(c);
        }
    }

    #[test]
    fn a_panic_becomes_an_internal_result() -> Checked {
        let rt = runtime();
        let c = client(rt, "http://127.0.0.1:1");

        guard(unsafe { &*c }.push_panic(4), || panic!("boom"));

        let [(tag, kind, grpc_code, data)] = only(events(rt, 1))?;
        assert_eq!(
            (tag, kind, grpc_code),
            (4, KIND_RPC_RESULT, Code::Internal as i32)
        );
        assert_eq!(message(&data), b"Panic in temporal-php-bridge: boom");
        unsafe { tpb_client_free(c) };
        release(rt);
        Ok(())
    }

    #[test]
    fn endpoint_reads_tls_and_rejects_an_invalid_url() {
        let endpoint_of = |target_url: &str, tls: &str| {
            let json =
                format!(r#"{{"target_url":"{target_url}","tls":{tls},"connect_timeout_ms":1}}"#);
            parse(json.as_bytes(), "client config").and_then(target)
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
    fn request_reads_ascii_and_base64_binary_metadata() -> Checked {
        let metadata = br#"{"Client-Name":["php"],"trace-bin":["AAEC"]}"#;
        let prepared = request(vec![1], metadata, timeout(1500))?;
        let client_name = prepared.metadata().get("client-name");
        assert_eq!(client_name.and_then(|v| v.to_str().ok()), Some("php"));
        let trace = prepared.metadata().get_bin("trace-bin");
        let trace = trace.and_then(|v| v.to_bytes().ok());
        assert_eq!(trace.as_deref(), Some(&[0, 1, 2][..]));
        assert!(request(vec![], br#"{"bad key":["x"]}"#, None).is_err());
        assert!(request(vec![], br#"{"key":"x"}"#, None).is_err());
        assert!(request(vec![], br#"{"key":[1]}"#, None).is_err());
        Ok(())
    }

    #[test]
    fn timeout_is_capped_to_the_longest_grpc_timeout() -> Checked {
        assert_eq!(timeout(0), None);
        assert_eq!(timeout(1500), Some(Duration::from_millis(1500)));
        assert_eq!(timeout(u64::MAX), Some(MAX_GRPC_TIMEOUT));
        let capped = request(vec![], b"", timeout(u64::MAX))?;
        let grpc_timeout = capped.metadata().get("grpc-timeout");
        assert_eq!(
            grpc_timeout.and_then(|v| v.to_str().ok()),
            Some("99999999H")
        );
        Ok(())
    }

    #[test]
    fn expired_deadline_is_deadline_exceeded() -> Checked {
        let rt = tokio::runtime::Builder::new_current_thread()
            .enable_time()
            .build()?;
        let timeout = Some(Duration::from_millis(10));
        let pending = rt.block_on(with_deadline(std::future::pending(), timeout));
        assert_eq!(
            pending.err().map(|s| s.code()),
            Some(Code::DeadlineExceeded)
        );
        let expired_status = Status::from_error(Box::new(TimeoutExpired(())));
        let transport_error = std::io::Error::other(expired_status);
        let expired = rt.block_on(with_deadline(
            async { Err(Status::from_error(Box::new(transport_error))) },
            timeout,
        ));
        let expired = expired.err().map(|s| (s.code(), s.message().to_owned()));
        assert_eq!(
            expired,
            Some((Code::DeadlineExceeded, DEADLINE_EXCEEDED.to_owned()))
        );
        let cancelled = rt.block_on(with_deadline(
            async { Err(Status::cancelled("cancelled by the server")) },
            timeout,
        ));
        assert_eq!(cancelled.err().map(|s| s.code()), Some(Code::Cancelled));
        Ok(())
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
    fn connect_reports_unavailable_and_timeout() -> Checked {
        let rt = tokio::runtime::Builder::new_current_thread()
            .enable_all()
            .build()?;
        let tcp = |uri| Target {
            endpoint: Endpoint::from_static(uri),
            socket: None,
        };
        let refused = rt.block_on(connect(tcp("http://127.0.0.1:1"), Duration::from_secs(5)));
        assert_eq!(refused.err().map(|s| s.code()), Some(Code::Unavailable));
        let timeout = rt.block_on(connect(tcp("http://192.0.2.1:7233"), Duration::ZERO));
        assert_eq!(
            timeout.err().map(|s| s.code()),
            Some(Code::DeadlineExceeded)
        );
        Ok(())
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
