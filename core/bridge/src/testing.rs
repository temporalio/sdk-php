use crate::ffi::{TpbEvent, slice, tpb_bytes_free};
use crate::queue::tpb_next_events;
use crate::replay::tpb_replayer_new;
use crate::runtime::{TpbRuntime, tpb_runtime_new};
use crate::worker::TpbWorker;
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

pub fn events(rt: *mut TpbRuntime, count: usize) -> Vec<Event> {
    let mut events = Vec::new();
    while events.len() < count {
        let mut buf: [TpbEvent; 8] = empty_events();
        let taken = unsafe { tpb_next_events(rt, 5_000, buf.as_mut_ptr(), buf.len()) };
        assert!(taken > 0, "no event in 5 seconds, received {events:?}");
        for e in &buf[..taken] {
            events.push((
                e.tag,
                e.kind,
                e.status,
                unsafe { slice(e.data.cast(), e.len) }.to_vec(),
            ));
            unsafe { tpb_bytes_free(e.data, e.len) };
        }
    }
    events.sort();
    events
}

pub fn grpc_server() -> String {
    let listener = std::net::TcpListener::bind("127.0.0.1:0").unwrap();
    listener.set_nonblocking(true).unwrap();
    let address = format!("http://{}", listener.local_addr().unwrap());
    std::thread::spawn(move || {
        let rt = tokio::runtime::Builder::new_current_thread()
            .enable_all()
            .build()
            .unwrap();
        rt.block_on(async move {
            let listener = tokio::net::TcpListener::from_std(listener).unwrap();
            while let Ok((socket, _)) = listener.accept().await {
                tokio::spawn(answer_ok(socket));
            }
        })
    });
    address
}

async fn answer_ok(socket: tokio::net::TcpStream) {
    let Ok(mut connection) = h2::server::handshake(socket).await else {
        return;
    };
    while let Some(Ok((_, mut respond))) = connection.accept().await {
        let response = http::Response::builder()
            .header("content-type", "application/grpc")
            .body(())
            .unwrap();
        let Ok(mut stream) = respond.send_response(response, false) else {
            continue;
        };
        let _ = stream.send_data(Bytes::from_static(&[0; 5]), false);
        let _ = stream.send_trailers(http::HeaderMap::from_iter([(
            http::HeaderName::from_static("grpc-status"),
            http::HeaderValue::from_static("0"),
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
    json.as_object_mut().unwrap().remove("connection");
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
    if !w.is_null() {
        return Ok(w);
    }
    let message = String::from_utf8(unsafe { slice(err.cast(), err_len) }.to_vec()).unwrap();
    unsafe { tpb_bytes_free(err, err_len) };
    Err(message)
}

pub fn replayer(rt: *mut TpbRuntime, with_workflow_task: bool) -> *mut TpbWorker {
    new_replayer(rt, &replay_config(), &history(with_workflow_task), b"wf").unwrap()
}
