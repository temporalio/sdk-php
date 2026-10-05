use crate::config::{WorkerJson, parse};
use crate::ffi::{bytes, construct, required};
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
    construct(err, err_len, || {
        new_replayer(
            required(rt, "runtime")?,
            bytes(config, config_len),
            bytes(history, history_len),
            bytes(workflow_id, workflow_id_len),
        )
    })
}

#[cfg(test)]
mod tests {
    use crate::ffi::{
        CALL_OK, KIND_SHUTDOWN_FINALIZED, KIND_WORKFLOW_ACTIVATION, STATUS_ERROR, STATUS_OK,
        STATUS_SHUTDOWN, release,
    };
    use crate::testing::{
        events, history, new_replayer, pending_events, replay_config, replayer, runtime,
    };
    use crate::worker::{
        tpb_poll_workflow_activation, tpb_worker_finalize_shutdown, tpb_worker_free,
        tpb_worker_initiate_shutdown,
    };

    #[test]
    fn finalize_shuts_down_a_replayer_with_a_poll_in_flight() {
        let rt = runtime();
        let w = replayer(rt, false);
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
            release(rt);
        }
    }

    #[test]
    fn invalid_config_history_and_workflow_id_return_an_error() {
        let rt = runtime();
        let error = |config: &str, history: &[u8], workflow_id: &[u8]| {
            new_replayer(rt, config, history, workflow_id).unwrap_err()
        };

        assert!(error("{}", &history(false), b"wf").starts_with("Invalid worker config JSON: "));
        assert!(error(&replay_config(), b"\xff", b"wf").starts_with("Invalid history: "));
        assert!(
            error(&replay_config(), &history(false), b"\xff").starts_with("Invalid workflow ID: ")
        );
        release(rt);
    }

    #[test]
    fn null_runtime_is_an_error() {
        let error = new_replayer(
            std::ptr::null_mut(),
            &replay_config(),
            &history(false),
            b"wf",
        );

        assert_eq!(error, Err("The runtime pointer is null".to_owned()));
    }
}
