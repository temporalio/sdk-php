use crate::config::{WorkerJson, parse};
use crate::ffi::{guard, into_ffi, slice};
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
    let workflow_id = String::from_utf8_lossy(workflow_id);
    let input = ReplayWorkerInput::new(
        config.worker_config()?,
        futures_util::stream::iter([HistoryForReplay::new(history, workflow_id)]),
    );
    let worker = {
        let _guard = rt.queue.handle.enter();
        temporalio_sdk_core::init_replay_worker(input)
            .map_err(|e| format!("Replay worker start failed: {e}"))?
    };
    Ok(TpbWorker::new(Some(worker), rt.queue.clone()))
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
    guard(
        |message| unsafe { into_ffi(Err(message), err, err_len) },
        || unsafe {
            into_ffi(
                new_replayer(
                    &*rt,
                    slice(config, config_len),
                    slice(history, history_len),
                    slice(workflow_id, workflow_id_len),
                ),
                err,
                err_len,
            )
        },
    )
}
