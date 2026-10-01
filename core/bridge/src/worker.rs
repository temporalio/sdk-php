use crate::config::{WorkerJson, parse};
use crate::ffi::{
    KIND_ACTIVITY_COMPLETED, KIND_ACTIVITY_TASK, KIND_SHUTDOWN_FINALIZED, KIND_WORKFLOW_ACTIVATION,
    KIND_WORKFLOW_COMPLETED, STATUS_ERROR, STATUS_OK, free, guard, into_ffi, slice,
};
use crate::queue::{Queue, error_status};
use crate::runtime::TpbRuntime;
use prost::Message;
use std::sync::Arc;
use temporalio_client::Connection;
use temporalio_common::protos::coresdk::{
    ActivityHeartbeat, ActivityTaskCompletion, workflow_completion::WorkflowActivationCompletion,
};
use temporalio_sdk_core::Worker;
use tokio::sync::{OwnedRwLockReadGuard, RwLock};

const FINALIZED: &str = "Worker is already finalized";
const CALL_OK: i32 = 0;
const CALL_FAILED: i32 = 1;

pub struct TpbWorker {
    worker: Arc<RwLock<Option<Worker>>>,
    queue: Arc<Queue>,
}

impl TpbWorker {
    pub fn new(worker: Option<Worker>, queue: Arc<Queue>) -> Self {
        Self {
            worker: Arc::new(RwLock::new(worker)),
            queue,
        }
    }

    fn core(&self, tag: u64, kind: i32) -> Option<OwnedRwLockReadGuard<Option<Worker>, Worker>> {
        let core = self
            .worker
            .clone()
            .try_read_owned()
            .ok()
            .and_then(|worker| OwnedRwLockReadGuard::try_map(worker, Option::as_ref).ok());
        if core.is_none() {
            self.queue
                .push(tag, kind, STATUS_ERROR, FINALIZED.as_bytes().to_vec());
        }
        core
    }

    fn call(&self, body: impl FnOnce(&Worker)) -> i32 {
        let Ok(worker) = self.worker.try_read() else {
            return CALL_FAILED;
        };
        let Some(core) = worker.as_ref() else {
            return CALL_FAILED;
        };
        let _guard = self.queue.handle.enter();
        body(core);
        CALL_OK
    }
}

fn new_worker(rt: &TpbRuntime, config: &[u8]) -> Result<TpbWorker, String> {
    let config: WorkerJson = parse(config, "worker config")?;
    let worker_config = config.worker_config()?;
    let options = config.connection.options()?;
    let worker = rt.queue.handle.block_on(async {
        let connection = Connection::connect(options)
            .await
            .map_err(|e| format!("Connection failed: {e}"))?;
        let worker = temporalio_sdk_core::init_worker(&rt.core, worker_config, connection)
            .map_err(|e| format!("Worker start failed: {e}"))?;
        worker
            .validate()
            .await
            .map_err(|e| format!("Worker validation failed: {e}"))?;
        Ok::<_, String>(worker)
    })?;
    Ok(TpbWorker::new(Some(worker), rt.queue.clone()))
}

#[unsafe(no_mangle)]
pub unsafe extern "C" fn tpb_worker_new(
    rt: *mut TpbRuntime,
    config: *const libc::c_char,
    config_len: usize,
    err: *mut *mut u8,
    err_len: *mut usize,
) -> *mut TpbWorker {
    guard(
        |message| unsafe { into_ffi(Err(message), err, err_len) },
        || unsafe { into_ffi(new_worker(&*rt, slice(config, config_len)), err, err_len) },
    )
}

#[unsafe(no_mangle)]
pub unsafe extern "C" fn tpb_poll_workflow_activation(w: *mut TpbWorker, tag: u64) {
    guard(
        |_| (),
        || {
            let w = unsafe { &*w };
            let Some(core) = w.core(tag, KIND_WORKFLOW_ACTIVATION) else {
                return;
            };
            let queue = w.queue.clone();
            w.queue
                .spawn(tag, KIND_WORKFLOW_ACTIVATION, error_status, async move {
                    let result = core.poll_workflow_activation().await;
                    drop(core);
                    queue.push_poll(tag, KIND_WORKFLOW_ACTIVATION, result);
                });
        },
    )
}

#[unsafe(no_mangle)]
pub unsafe extern "C" fn tpb_poll_activity_task(w: *mut TpbWorker, tag: u64) {
    guard(
        |_| (),
        || {
            let w = unsafe { &*w };
            let Some(core) = w.core(tag, KIND_ACTIVITY_TASK) else {
                return;
            };
            let queue = w.queue.clone();
            w.queue
                .spawn(tag, KIND_ACTIVITY_TASK, error_status, async move {
                    let result = core.poll_activity_task().await;
                    drop(core);
                    queue.push_poll(tag, KIND_ACTIVITY_TASK, result);
                });
        },
    )
}

#[unsafe(no_mangle)]
pub unsafe extern "C" fn tpb_complete_workflow_activation(
    w: *mut TpbWorker,
    tag: u64,
    data: *const libc::c_char,
    len: usize,
) {
    guard(
        |_| (),
        || {
            let w = unsafe { &*w };
            let completion = match WorkflowActivationCompletion::decode(unsafe { slice(data, len) })
            {
                Ok(completion) => completion,
                Err(e) => {
                    return w.queue.push_error(
                        tag,
                        KIND_WORKFLOW_COMPLETED,
                        Err(format!("Decode failure: {e}")),
                    );
                }
            };
            let Some(core) = w.core(tag, KIND_WORKFLOW_COMPLETED) else {
                return;
            };
            let queue = w.queue.clone();
            w.queue
                .spawn(tag, KIND_WORKFLOW_COMPLETED, error_status, async move {
                    let result = core.complete_workflow_activation(completion).await;
                    drop(core);
                    queue.push_error(
                        tag,
                        KIND_WORKFLOW_COMPLETED,
                        result.map_err(|e| e.to_string()),
                    );
                });
        },
    )
}

#[unsafe(no_mangle)]
pub unsafe extern "C" fn tpb_complete_activity_task(
    w: *mut TpbWorker,
    tag: u64,
    data: *const libc::c_char,
    len: usize,
) {
    guard(
        |_| (),
        || {
            let w = unsafe { &*w };
            let completion = match ActivityTaskCompletion::decode(unsafe { slice(data, len) }) {
                Ok(completion) => completion,
                Err(e) => {
                    return w.queue.push_error(
                        tag,
                        KIND_ACTIVITY_COMPLETED,
                        Err(format!("Decode failure: {e}")),
                    );
                }
            };
            let Some(core) = w.core(tag, KIND_ACTIVITY_COMPLETED) else {
                return;
            };
            let queue = w.queue.clone();
            w.queue
                .spawn(tag, KIND_ACTIVITY_COMPLETED, error_status, async move {
                    let result = core.complete_activity_task(completion).await;
                    drop(core);
                    queue.push_error(
                        tag,
                        KIND_ACTIVITY_COMPLETED,
                        result.map_err(|e| e.to_string()),
                    );
                });
        },
    )
}

#[unsafe(no_mangle)]
pub unsafe extern "C" fn tpb_record_activity_heartbeat(
    w: *mut TpbWorker,
    data: *const libc::c_char,
    len: usize,
) -> i32 {
    guard(
        |_| CALL_FAILED,
        || {
            let Ok(heartbeat) = ActivityHeartbeat::decode(unsafe { slice(data, len) }) else {
                return CALL_FAILED;
            };
            unsafe { &*w }.call(|core| core.record_activity_heartbeat(heartbeat))
        },
    )
}

#[unsafe(no_mangle)]
pub unsafe extern "C" fn tpb_request_workflow_eviction(
    w: *mut TpbWorker,
    run_id: *const libc::c_char,
    len: usize,
) -> i32 {
    guard(
        |_| CALL_FAILED,
        || {
            let run_id = String::from_utf8_lossy(unsafe { slice(run_id, len) });
            unsafe { &*w }.call(|core| core.request_workflow_eviction(&run_id))
        },
    )
}

#[unsafe(no_mangle)]
pub unsafe extern "C" fn tpb_worker_initiate_shutdown(w: *mut TpbWorker) -> i32 {
    guard(
        |_| CALL_FAILED,
        || unsafe { &*w }.call(Worker::initiate_shutdown),
    )
}

#[unsafe(no_mangle)]
pub unsafe extern "C" fn tpb_worker_finalize_shutdown(w: *mut TpbWorker, tag: u64) {
    guard(
        |_| (),
        || {
            let w = unsafe { &*w };
            let worker = w.worker.clone();
            let queue = w.queue.clone();
            w.queue
                .spawn(tag, KIND_SHUTDOWN_FINALIZED, error_status, async move {
                    let core = worker.write().await.take();
                    let Some(core) = core else {
                        return queue.push_error(
                            tag,
                            KIND_SHUTDOWN_FINALIZED,
                            Err(FINALIZED.into()),
                        );
                    };
                    core.finalize_shutdown().await;
                    queue.push(tag, KIND_SHUTDOWN_FINALIZED, STATUS_OK, Vec::new());
                });
        },
    )
}

#[unsafe(no_mangle)]
pub unsafe extern "C" fn tpb_worker_free(w: *mut TpbWorker) {
    unsafe { free(w) }
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::ffi::{TpbEvent, tpb_bytes_free};
    use crate::queue::tpb_next_events;
    use crate::runtime::{tpb_runtime_free, tpb_runtime_new};

    #[test]
    fn finalized_worker_and_panicking_task_report_error_events() {
        let config = br#"{"threads":1,"log":null}"#;
        let rt = unsafe {
            tpb_runtime_new(
                config.as_ptr().cast(),
                config.len(),
                std::ptr::null_mut(),
                std::ptr::null_mut(),
            )
        };
        let queue = unsafe { &*rt }.queue.clone();
        let w = Box::into_raw(Box::new(TpbWorker::new(None, queue.clone())));
        unsafe {
            tpb_poll_workflow_activation(w, 1);
            tpb_complete_activity_task(w, 2, std::ptr::null(), 0);
            tpb_worker_finalize_shutdown(w, 3);
            assert_eq!(tpb_worker_initiate_shutdown(w), CALL_FAILED);
            assert_eq!(
                tpb_request_workflow_eviction(w, std::ptr::null(), 0),
                CALL_FAILED
            );
            assert_eq!(
                tpb_record_activity_heartbeat(w, std::ptr::null(), 0),
                CALL_FAILED
            );
        }
        queue.spawn(4, KIND_ACTIVITY_TASK, error_status, async {
            panic!("boom")
        });

        let mut events = Vec::new();
        while events.len() < 4 {
            let mut buf: [TpbEvent; 8] = std::array::from_fn(|_| TpbEvent {
                tag: 0,
                kind: 0,
                status: 0,
                data: std::ptr::null_mut(),
                len: 0,
            });
            let count = unsafe { tpb_next_events(rt, 5_000, buf.as_mut_ptr(), buf.len()) };
            assert!(count > 0);
            for e in &buf[..count] {
                let text =
                    String::from_utf8_lossy(unsafe { slice(e.data.cast(), e.len) }).into_owned();
                events.push((e.tag, e.kind, e.status, text));
                unsafe { tpb_bytes_free(e.data, e.len) };
            }
        }
        events.sort();
        assert_eq!(
            events,
            vec![
                (
                    1,
                    KIND_WORKFLOW_ACTIVATION,
                    STATUS_ERROR,
                    FINALIZED.to_owned()
                ),
                (
                    2,
                    KIND_ACTIVITY_COMPLETED,
                    STATUS_ERROR,
                    FINALIZED.to_owned()
                ),
                (
                    3,
                    KIND_SHUTDOWN_FINALIZED,
                    STATUS_ERROR,
                    FINALIZED.to_owned()
                ),
                (
                    4,
                    KIND_ACTIVITY_TASK,
                    STATUS_ERROR,
                    "Panic in temporal-php-bridge: boom".to_owned()
                ),
            ]
        );
        unsafe {
            tpb_worker_free(w);
            tpb_runtime_free(rt);
        }
    }
}
