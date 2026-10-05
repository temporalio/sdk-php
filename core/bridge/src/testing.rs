use crate::ffi::{TpbEvent, bytes, tpb_bytes_free};
use crate::queue::tpb_next_events;
use crate::replay::tpb_replayer_new;
use crate::runtime::{TpbRuntime, tpb_runtime_new};
use crate::worker::{TpbWorker, tpb_worker_new};
use prost::{Message, bytes::Bytes};
use temporalio_common::protos::temporal::api::{
    common::v1::WorkflowType,
    enums::v1::EventType,
    history::v1::{
        History, HistoryEvent, WorkflowExecutionStartedEventAttributes, history_event::Attributes,
    },
    taskqueue::v1::TaskQueue,
};
use tonic::codegen::http;

pub type Event = (u64, i32, i32, Vec<u8>);

pub type Checked = Result<(), Box<dyn std::error::Error>>;

pub const GRPC_OK: &str = "0";

pub fn only<const N: usize>(events: Vec<Event>) -> Result<[Event; N], String> {
    events
        .try_into()
        .map_err(|events| format!("expected {N} events, received {events:?}"))
}

pub fn runtime() -> *mut TpbRuntime {
    let config = br#"{"threads":1,"log":"off"}"#;
    let rt = unsafe {
        tpb_runtime_new(
            config.as_ptr().cast(),
            config.len(),
            std::ptr::null_mut(),
            std::ptr::null_mut(),
        )
    };
    assert!(!rt.is_null());
    rt
}

pub fn empty_events<const N: usize>() -> [TpbEvent; N] {
    std::array::from_fn(|_| TpbEvent {
        tag: 0,
        kind: 0,
        status: 0,
        data: std::ptr::null_mut(),
        len: 0,
    })
}

pub fn pending_events(rt: *mut TpbRuntime) -> usize {
    let mut buf: [TpbEvent; 1] = empty_events();
    unsafe { tpb_next_events(rt, 300, buf.as_mut_ptr(), buf.len()) }
}

fn take_events(rt: *mut TpbRuntime, timeout_ms: u32) -> Vec<Event> {
    let mut buf: [TpbEvent; 8] = empty_events();
    let taken = unsafe { tpb_next_events(rt, timeout_ms, buf.as_mut_ptr(), buf.len()) };
    buf[..taken]
        .iter()
        .map(|e| {
            let event = (
                e.tag,
                e.kind,
                e.status,
                bytes(e.data.cast(), e.len).to_vec(),
            );
            unsafe { tpb_bytes_free(e.data, e.len) };
            event
        })
        .collect()
}

pub fn events(rt: *mut TpbRuntime, count: usize) -> Vec<Event> {
    let mut events = Vec::new();
    while events.len() < count {
        let taken = take_events(rt, 5_000);
        assert!(
            !taken.is_empty(),
            "no event in 5 seconds, received {events:?}"
        );
        events.extend(taken);
    }
    events.sort();
    events
}

pub fn drain_events(rt: *mut TpbRuntime) -> Vec<Event> {
    let mut events = Vec::new();
    loop {
        let taken = take_events(rt, 0);
        if taken.is_empty() {
            return events;
        }
        events.extend(taken);
    }
}

pub fn grpc_server() -> std::io::Result<String> {
    grpc_server_with(|_| GRPC_OK)
}

pub fn grpc_server_with(status: fn(&str) -> &'static str) -> std::io::Result<String> {
    let listener = std::net::TcpListener::bind("127.0.0.1:0")?;
    listener.set_nonblocking(true)?;
    let address = format!("http://{}", listener.local_addr()?);
    std::thread::spawn(move || -> std::io::Result<()> {
        let rt = tokio::runtime::Builder::new_current_thread()
            .enable_all()
            .build()?;
        rt.block_on(async move {
            let listener = tokio::net::TcpListener::from_std(listener)?;
            loop {
                let (socket, _) = listener.accept().await?;
                tokio::spawn(answer(socket, status));
            }
        })
    });
    Ok(address)
}

async fn answer(socket: tokio::net::TcpStream, status: fn(&str) -> &'static str) {
    let Ok(mut connection) = h2::server::handshake(socket).await else {
        return;
    };
    while let Some(Ok((request, mut respond))) = connection.accept().await {
        let grpc_status = status(request.uri().path());
        let mut response = http::Response::new(());
        response.headers_mut().insert(
            http::header::CONTENT_TYPE,
            http::HeaderValue::from_static("application/grpc"),
        );
        let Ok(mut stream) = respond.send_response(response, false) else {
            continue;
        };
        let _ = stream.send_data(Bytes::from_static(&[0; 5]), false);
        let _ = stream.send_trailers(http::HeaderMap::from_iter([(
            http::HeaderName::from_static("grpc-status"),
            http::HeaderValue::from_static(grpc_status),
        )]));
    }
}

pub fn worker_json(target_url: &str) -> serde_json::Value {
    serde_json::json!({
        "connection": {
            "target_url": target_url,
            "client_name": "temporal-php-2",
            "client_version": "2.0.0",
            "identity": "1@host",
            "api_key": null,
            "tls": null,
            "connect_timeout_ms": 10000,
            "grpc_compression": "gzip",
        },
        "namespace": "default",
        "task_queue": "q",
        "workflows": true,
        "local_activities": true,
        "remote_activities": false,
        "deployment": null,
        "build_id": "",
        "graceful_shutdown_period_ms": 1500,
        "max_worker_activities_per_second": 2.5,
        "max_task_queue_activities_per_second": null,
        "max_cached_workflows": 10000,
        "max_outstanding_workflow_tasks": 100,
        "max_outstanding_activities": 1,
        "max_outstanding_local_activities": 1,
        "max_concurrent_workflow_task_polls": 8,
        "max_concurrent_activity_task_polls": 1,
        "nonsticky_to_sticky_poll_ratio": 0.5,
        "sticky_queue_schedule_to_start_timeout_ms": 5000,
        "nondeterminism_fails_workflow": false,
        "max_heartbeat_throttle_interval_ms": null,
        "poller_autoscaling": false,
    })
}

pub fn replay_config() -> String {
    let mut json = worker_json("");
    if let Some(fields) = json.as_object_mut() {
        fields.remove("connection");
    }
    json.to_string()
}

pub fn history(with_workflow_task: bool) -> Vec<u8> {
    let started = WorkflowExecutionStartedEventAttributes {
        workflow_type: Some(WorkflowType {
            name: "Replayed".into(),
        }),
        original_execution_run_id: "run".into(),
        task_queue: Some(TaskQueue {
            name: "q".into(),
            ..Default::default()
        }),
        ..Default::default()
    };
    let mut events = vec![(
        EventType::WorkflowExecutionStarted,
        Attributes::WorkflowExecutionStartedEventAttributes(started),
    )];
    if with_workflow_task {
        events.push((
            EventType::WorkflowTaskScheduled,
            Attributes::WorkflowTaskScheduledEventAttributes(Default::default()),
        ));
        events.push((
            EventType::WorkflowTaskStarted,
            Attributes::WorkflowTaskStartedEventAttributes(Default::default()),
        ));
    }
    History {
        events: (1..)
            .zip(events)
            .map(|(event_id, (event_type, attributes))| HistoryEvent {
                event_id,
                event_type: event_type as i32,
                attributes: Some(attributes),
                ..Default::default()
            })
            .collect(),
    }
    .encode_to_vec()
}

pub fn new_replayer(
    rt: *mut TpbRuntime,
    config: &str,
    history: &[u8],
    workflow_id: &[u8],
) -> Result<*mut TpbWorker, String> {
    let (mut err, mut err_len) = (std::ptr::null_mut(), 0);
    let w = unsafe {
        tpb_replayer_new(
            rt,
            config.as_ptr().cast(),
            config.len(),
            history.as_ptr().cast(),
            history.len(),
            workflow_id.as_ptr().cast(),
            workflow_id.len(),
            &mut err,
            &mut err_len,
        )
    };
    created(w, err, err_len)
}

pub fn start_worker(
    rt: *mut TpbRuntime,
    json: &serde_json::Value,
) -> Result<*mut TpbWorker, String> {
    let config = json.to_string();
    let (mut err, mut err_len) = (std::ptr::null_mut(), 0);
    let w = unsafe {
        tpb_worker_new(
            rt,
            config.as_ptr().cast(),
            config.len(),
            &mut err,
            &mut err_len,
        )
    };
    created(w, err, err_len)
}

fn created<T>(object: *mut T, err: *mut u8, err_len: usize) -> Result<*mut T, String> {
    if !object.is_null() {
        return Ok(object);
    }
    let message = String::from_utf8_lossy(bytes(err.cast(), err_len)).into_owned();
    unsafe { tpb_bytes_free(err, err_len) };
    Err(message)
}

pub fn replayer(rt: *mut TpbRuntime, with_workflow_task: bool) -> Result<*mut TpbWorker, String> {
    new_replayer(rt, &replay_config(), &history(with_workflow_task), b"wf")
}
