use crate::config::{WorkerJson, parse};
use crate::ffi::{construct, slice};
use crate::runtime::TpbRuntime;
use crate::worker::TpbWorker;
use prost::Message;
use temporalio_common::protos::temporal::api::history::v1::History;
use temporalio_sdk_core::replay::{HistoryForReplay, ReplayWorkerInput};

fn new_replayer(
    rt: &TpbRuntime,
    config: &[u8],
    history: &[u8],
    workflow_id: &[u8],
) -> Result<TpbWorker, String> {
    let config: WorkerJson = parse(config, "worker config")?;
    let history = History::decode(history).map_err(|e| format!("Invalid history: {e}"))?;
    let workflow_id = std::str::from_utf8(workflow_id)
        .map_err(|e| format!("Invalid workflow ID: {e}"))?
        .to_owned();
    let input = ReplayWorkerInput::new(
        config.worker_config()?,
        futures_util::stream::iter([HistoryForReplay::new(history, workflow_id)]),
    );
    let worker = {
        let _guard = rt.queue.handle.enter();
        temporalio_sdk_core::init_replay_worker(input)
            .map_err(|e| format!("Replay worker start failed: {e}"))?
    };
    Ok(TpbWorker::new(worker, rt.queue.clone()))
}

#[unsafe(no_mangle)]
pub unsafe extern "C" fn tpb_replayer_new(
    rt: *mut TpbRuntime,
    config: *const libc::c_char,
    config_len: usize,
    history: *const libc::c_char,
    history_len: usize,
    workflow_id: *const libc::c_char,
    workflow_id_len: usize,
    err: *mut *mut u8,
    err_len: *mut usize,
) -> *mut TpbWorker {
    unsafe {
        construct(err, err_len, || {
            new_replayer(
                &*rt,
                slice(config, config_len),
                slice(history, history_len),
                slice(workflow_id, workflow_id_len),
            )
        })
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::ffi::{
        CALL_OK, KIND_SHUTDOWN_FINALIZED, KIND_WORKFLOW_ACTIVATION, STATUS_ERROR, STATUS_OK,
        STATUS_SHUTDOWN, free, tpb_bytes_free,
    };
    use crate::testing::{events, pending_events, runtime};
    use crate::worker::{
        tpb_poll_workflow_activation, tpb_worker_finalize_shutdown, tpb_worker_free,
        tpb_worker_initiate_shutdown,
    };
    use temporalio_common::protos::temporal::api::{
        common::v1::WorkflowType,
        enums::v1::EventType,
        history::v1::{
            HistoryEvent, WorkflowExecutionStartedEventAttributes, history_event::Attributes,
        },
        taskqueue::v1::TaskQueue,
    };

    const CONFIG: &str = r#"{
        "namespace": "default",
        "task_queue": "replay",
        "workflows": true,
        "local_activities": false,
        "remote_activities": false,
        "deployment": null,
        "build_id": "",
        "graceful_shutdown_period_ms": 0,
        "max_worker_activities_per_second": null,
        "max_task_queue_activities_per_second": null,
        "max_cached_workflows": 1,
        "max_outstanding_workflow_tasks": 2,
        "max_outstanding_activities": 1,
        "max_outstanding_local_activities": 1,
        "max_concurrent_workflow_task_polls": 2,
        "max_concurrent_activity_task_polls": 1,
        "nonsticky_to_sticky_poll_ratio": 1.0,
        "sticky_queue_schedule_to_start_timeout_ms": 5000,
        "nondeterminism_fails_workflow": false,
        "max_heartbeat_throttle_interval_ms": null,
        "poller_autoscaling": false
    }"#;

    fn history() -> Vec<u8> {
        History {
            events: vec![HistoryEvent {
                event_id: 1,
                event_type: EventType::WorkflowExecutionStarted as i32,
                attributes: Some(Attributes::WorkflowExecutionStartedEventAttributes(
                    WorkflowExecutionStartedEventAttributes {
                        workflow_type: Some(WorkflowType {
                            name: "Replayed".into(),
                        }),
                        original_execution_run_id: "run".into(),
                        task_queue: Some(TaskQueue {
                            name: "replay".into(),
                            ..Default::default()
                        }),
                        ..Default::default()
                    },
                )),
                ..Default::default()
            }],
        }
        .encode_to_vec()
    }

    fn replayer(
        rt: *mut TpbRuntime,
        history: &[u8],
        workflow_id: &[u8],
        err: *mut *mut u8,
        err_len: *mut usize,
    ) -> *mut TpbWorker {
        unsafe {
            tpb_replayer_new(
                rt,
                CONFIG.as_ptr().cast(),
                CONFIG.len(),
                history.as_ptr().cast(),
                history.len(),
                workflow_id.as_ptr().cast(),
                workflow_id.len(),
                err,
                err_len,
            )
        }
    }

    fn replay_worker(
        rt: *mut TpbRuntime,
        history: &[u8],
        workflow_id: &[u8],
    ) -> Result<*mut TpbWorker, String> {
        let (mut err, mut err_len) = (std::ptr::null_mut(), 0);
        let w = replayer(rt, history, workflow_id, &mut err, &mut err_len);
        if !w.is_null() {
            return Ok(w);
        }
        let message = String::from_utf8(unsafe { slice(err.cast(), err_len) }.to_vec()).unwrap();
        unsafe { tpb_bytes_free(err, err_len) };
        Err(message)
    }

    fn replayer_error(history: &[u8], workflow_id: &[u8]) -> String {
        let rt = runtime();
        let message = replay_worker(rt, history, workflow_id).unwrap_err();
        unsafe { free(rt) };
        message
    }

    #[test]
    fn finalize_shuts_down_a_replayer_with_a_poll_in_flight() {
        let rt = runtime();
        let w = replay_worker(rt, &history(), b"wf").unwrap();
        unsafe { tpb_poll_workflow_activation(w, 1) };
        assert_eq!(pending_events(rt), 0);
        unsafe {
            tpb_worker_finalize_shutdown(w, 2);
            assert_eq!(tpb_worker_initiate_shutdown(w), CALL_OK);
        }
        assert_eq!(
            events(rt, 2),
            vec![
                (1, KIND_WORKFLOW_ACTIVATION, STATUS_SHUTDOWN, vec![]),
                (2, KIND_SHUTDOWN_FINALIZED, STATUS_OK, vec![]),
            ]
        );
        unsafe { tpb_worker_finalize_shutdown(w, 3) };
        assert_eq!(
            events(rt, 1),
            vec![(
                3,
                KIND_SHUTDOWN_FINALIZED,
                STATUS_ERROR,
                b"Worker is already finalized".to_vec()
            )]
        );
        unsafe {
            tpb_worker_free(w);
            free(rt);
        }
    }

    #[test]
    fn invalid_history_and_workflow_id_return_an_error() {
        assert!(replayer_error(b"\xff", b"wf").starts_with("Invalid history: "));
        assert!(replayer_error(&history(), b"\xff").starts_with("Invalid workflow ID: "));
        let rt = runtime();
        let w = replayer(
            rt,
            b"\xff",
            b"wf",
            std::ptr::null_mut(),
            std::ptr::null_mut(),
        );
        assert!(w.is_null());
        unsafe { free(rt) };
    }
}
