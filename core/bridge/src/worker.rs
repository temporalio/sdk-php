use crate::config::WorkerJson;
use crate::ffi::{
    CALL_FAILED, CALL_OK, KIND_ACTIVITY_COMPLETED, KIND_ACTIVITY_TASK, KIND_SHUTDOWN_FINALIZED,
    KIND_WORKFLOW_ACTIVATION, KIND_WORKFLOW_COMPLETED, STATUS_ERROR, STATUS_OK, construct, free,
    guard, slice,
};
use crate::queue::{Queue, error_status};
use crate::runtime::TpbRuntime;
use prost::Message;
use std::{
    fmt::Display,
    future::Future,
    sync::{
        Arc,
        atomic::{AtomicBool, Ordering},
    },
};
use temporalio_client::Connection;
use temporalio_common::protos::coresdk::{
    ActivityHeartbeat, ActivityTaskCompletion, workflow_completion::WorkflowActivationCompletion,
};
use temporalio_sdk_core::{PollError, Worker};
use tokio::sync::{OwnedRwLockReadGuard, RwLock};

const FINALIZED: &str = "Worker is already finalized";

type Core = OwnedRwLockReadGuard<Option<Worker>, Worker>;

pub struct TpbWorker {
    worker: Arc<RwLock<Option<Worker>>>,
    shutdown_initiated: AtomicBool,
    queue: Arc<Queue>,
}

impl TpbWorker {
    pub fn new(worker: Worker, queue: Arc<Queue>) -> Self {
        Self {
            worker: Arc::new(RwLock::new(Some(worker))),
            shutdown_initiated: AtomicBool::new(false),
            queue,
        }
    }

    fn core(&self, tag: u64, kind: i32) -> Option<Core> {
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

    fn initiate_shutdown(&self) -> i32 {
        if self.shutdown_initiated.load(Ordering::Acquire) {
            return CALL_OK;
        }
        let status = self.call(Worker::initiate_shutdown);
        if status == CALL_OK {
            self.shutdown_initiated.store(true, Ordering::Release);
        }
        status
    }

    fn poll<T, F>(&self, tag: u64, kind: i32, poll: impl FnOnce(Core) -> F)
    where
        T: Message,
        F: Future<Output = Result<T, PollError>> + Send + 'static,
    {
        let Some(core) = self.core(tag, kind) else {
            return;
        };
        let queue = self.queue.clone();
        let result = poll(core);
        self.queue.spawn(tag, kind, error_status, async move {
            queue.push_poll(tag, kind, result.await);
        });
    }

    fn complete<C, E, F>(
        &self,
        tag: u64,
        kind: i32,
        data: &[u8],
        complete: impl FnOnce(Core, C) -> F,
    ) where
        C: Message + Default,
        E: Display,
        F: Future<Output = Result<(), E>> + Send + 'static,
    {
        let completion = match C::decode(data) {
            Ok(completion) => completion,
            Err(e) => {
                return self
                    .queue
                    .push_error(tag, kind, Err(format!("Decode failure: {e}")));
            }
        };
        let Some(core) = self.core(tag, kind) else {
            return;
        };
        let queue = self.queue.clone();
        let result = complete(core, completion);
        self.queue.spawn(tag, kind, error_status, async move {
            queue.push_error(tag, kind, result.await.map_err(|e| e.to_string()));
        });
    }
}

fn new_worker(rt: &TpbRuntime, config: &[u8]) -> Result<TpbWorker, String> {
    let (connection_key, connection, config) = WorkerJson::parse_with_connection(config)?;
    let worker_config = config.worker_config()?;
    let options = connection.options()?;
    let worker = rt.queue.handle.block_on(async {
        let cached = rt.connections.lock().unwrap().get(&connection_key).cloned();
        let connection = match cached {
            Some(connection) => connection,
            None => {
                let connection = Connection::connect(options)
                    .await
                    .map_err(|e| format!("Connection failed: {e}"))?;
                rt.connections
                    .lock()
                    .unwrap()
                    .insert(connection_key, connection.clone());
                connection
            }
        };
        let worker = temporalio_sdk_core::init_worker(&rt.core, worker_config, connection)
            .map_err(|e| format!("Worker start failed: {e}"))?;
        if let Err(e) = worker.validate().await {
            worker.initiate_shutdown();
            worker.finalize_shutdown().await;
            return Err(format!("Worker validation failed: {e}"));
        }
        Ok::<_, String>(worker)
    })?;
    Ok(TpbWorker::new(worker, rt.queue.clone()))
}

#[unsafe(no_mangle)]
pub unsafe extern "C" fn tpb_worker_new(
    rt: *mut TpbRuntime,
    config: *const libc::c_char,
    config_len: usize,
    err: *mut *mut u8,
    err_len: *mut usize,
) -> *mut TpbWorker {
    unsafe { construct(err, err_len, || new_worker(&*rt, slice(config, config_len))) }
}

#[unsafe(no_mangle)]
pub unsafe extern "C" fn tpb_poll_workflow_activation(w: *mut TpbWorker, tag: u64) {
    guard(
        |_| (),
        || {
            unsafe { &*w }.poll(tag, KIND_WORKFLOW_ACTIVATION, |core| async move {
                core.poll_workflow_activation().await
            })
        },
    )
}

#[unsafe(no_mangle)]
pub unsafe extern "C" fn tpb_poll_activity_task(w: *mut TpbWorker, tag: u64) {
    guard(
        |_| (),
        || {
            unsafe { &*w }.poll(tag, KIND_ACTIVITY_TASK, |core| async move {
                core.poll_activity_task().await
            })
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
            unsafe { &*w }.complete(
                tag,
                KIND_WORKFLOW_COMPLETED,
                unsafe { slice(data, len) },
                |core, completion: WorkflowActivationCompletion| async move {
                    core.complete_workflow_activation(completion).await
                },
            )
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
            unsafe { &*w }.complete(
                tag,
                KIND_ACTIVITY_COMPLETED,
                unsafe { slice(data, len) },
                |core, completion: ActivityTaskCompletion| async move {
                    core.complete_activity_task(completion).await
                },
            )
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
pub unsafe extern "C" fn tpb_worker_initiate_shutdown(w: *mut TpbWorker) -> i32 {
    guard(|_| CALL_FAILED, || unsafe { &*w }.initiate_shutdown())
}

#[unsafe(no_mangle)]
pub unsafe extern "C" fn tpb_worker_finalize_shutdown(w: *mut TpbWorker, tag: u64) {
    guard(
        |_| (),
        || {
            let w = unsafe { &*w };
            w.initiate_shutdown();
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
    use crate::ffi::STATUS_SHUTDOWN;
    use crate::testing::{
        events, grpc_server, grpc_server_with, pending_events, replayer, runtime, start_worker,
        worker_json,
    };
    use temporalio_common::protos::coresdk::{
        activity_result::ActivityExecutionResult,
        workflow_activation::WorkflowActivation,
        workflow_completion::{Success, workflow_activation_completion::Status},
    };

    const GRPC_OK: &str = "0";
    const GRPC_NOT_FOUND: &str = "5";

    fn worker(rt: *mut TpbRuntime, json: serde_json::Value) -> *mut TpbWorker {
        let w = start_worker(rt, &json);
        assert!(w.is_ok(), "{w:?}");
        w.unwrap_or(std::ptr::null_mut())
    }

    fn text(data: &[u8]) -> String {
        String::from_utf8_lossy(data).into_owned()
    }

    #[test]
    fn failed_validation_does_not_keep_the_task_queue_registered() {
        let rt = runtime();
        let server = grpc_server_with(|path| match path.ends_with("/DescribeNamespace") {
            true => GRPC_NOT_FOUND,
            false => GRPC_OK,
        });

        for _ in 0..2 {
            let error = start_worker(rt, &worker_json(&server)).err();
            assert!(
                error
                    .as_deref()
                    .is_some_and(|e| e.starts_with("Worker validation failed: ")),
                "{error:?}"
            );
        }
        unsafe { free(rt) };
    }

    #[test]
    fn workers_with_the_same_connection_share_one_connection() {
        let rt = runtime();
        let server = grpc_server();
        let first = worker(rt, worker_json(&server));
        let mut another_queue = worker_json(&server);
        another_queue["task_queue"] = "another".into();
        let second = worker(rt, another_queue);

        assert_eq!(unsafe { &*rt }.connections.lock().unwrap().len(), 1);
        unsafe {
            tpb_worker_finalize_shutdown(first, 1);
            tpb_worker_finalize_shutdown(second, 2);
        }
        assert_eq!(
            events(rt, 2),
            vec![
                (1, KIND_SHUTDOWN_FINALIZED, STATUS_OK, vec![]),
                (2, KIND_SHUTDOWN_FINALIZED, STATUS_OK, vec![]),
            ]
        );
        unsafe {
            tpb_worker_free(first);
            tpb_worker_free(second);
            free(rt);
        }
    }

    #[test]
    fn replayer_polls_and_completes_workflow_activations() {
        let rt = runtime();
        let w = replayer(rt, true);

        unsafe { tpb_poll_workflow_activation(w, 1) };
        let [(tag, kind, status, activation)] = events(rt, 1).try_into().unwrap();
        assert_eq!(
            (tag, kind, status),
            (1, KIND_WORKFLOW_ACTIVATION, STATUS_OK)
        );
        let run_id = WorkflowActivation::decode(&activation[..]).unwrap().run_id;
        assert_eq!(run_id, "run");

        let completion = WorkflowActivationCompletion {
            run_id,
            status: Some(Status::Successful(Success::default())),
            ..Default::default()
        }
        .encode_to_vec();
        let undecodable = b"\xff";
        unsafe {
            tpb_complete_workflow_activation(w, 2, completion.as_ptr().cast(), completion.len());
            tpb_complete_workflow_activation(w, 3, undecodable.as_ptr().cast(), 1);
        }
        let [(tag, kind, status, error)] = events(rt, 1).try_into().unwrap();
        assert_eq!(
            (tag, kind, status),
            (3, KIND_WORKFLOW_COMPLETED, STATUS_ERROR)
        );
        assert!(text(&error).starts_with("Decode failure: "));
        assert_eq!(pending_events(rt), 0);
        unsafe {
            tpb_worker_free(w);
            free(rt);
        }
    }

    #[test]
    fn replayer_has_no_activities_but_accepts_heartbeats() {
        let rt = runtime();
        let w = replayer(rt, false);
        let completion = ActivityTaskCompletion {
            task_token: vec![1],
            result: Some(ActivityExecutionResult::ok(Default::default())),
        }
        .encode_to_vec();
        let heartbeat = ActivityHeartbeat {
            task_token: vec![1],
            details: vec![],
        }
        .encode_to_vec();
        let undecodable = b"\xff";

        unsafe {
            tpb_poll_activity_task(w, 1);
            tpb_complete_activity_task(w, 2, completion.as_ptr().cast(), completion.len());
            assert_eq!(
                tpb_record_activity_heartbeat(w, heartbeat.as_ptr().cast(), heartbeat.len()),
                CALL_OK
            );
            assert_eq!(
                tpb_record_activity_heartbeat(w, undecodable.as_ptr().cast(), 1),
                CALL_FAILED
            );
        }

        let [poll, (tag, kind, status, error)] = events(rt, 2).try_into().unwrap();
        assert_eq!(poll, (1, KIND_ACTIVITY_TASK, STATUS_SHUTDOWN, vec![]));
        assert_eq!(
            (tag, kind, status),
            (2, KIND_ACTIVITY_COMPLETED, STATUS_ERROR)
        );
        assert_eq!(text(&error), "Activities are not enabled on this worker");
        unsafe {
            tpb_worker_free(w);
            free(rt);
        }
    }

    #[test]
    fn calls_fail_while_finalize_holds_the_worker_and_shutdown_is_idempotent() {
        let rt = runtime();
        let w = replayer(rt, false);
        let held = unsafe { &*w }.worker.clone().try_write_owned().unwrap();
        assert_eq!(
            unsafe { tpb_record_activity_heartbeat(w, std::ptr::null(), 0) },
            CALL_FAILED
        );
        assert_eq!(unsafe { tpb_worker_initiate_shutdown(w) }, CALL_FAILED);
        drop(held);
        assert_eq!(unsafe { tpb_worker_initiate_shutdown(w) }, CALL_OK);
        assert_eq!(unsafe { tpb_worker_initiate_shutdown(w) }, CALL_OK);

        unsafe { tpb_worker_finalize_shutdown(w, 1) };
        assert_eq!(
            events(rt, 1),
            vec![(1, KIND_SHUTDOWN_FINALIZED, STATUS_OK, vec![])]
        );
        unsafe {
            tpb_worker_free(w);
            free(rt);
        }
    }

    #[test]
    fn finalized_worker_and_panicking_task_report_error_events() {
        let rt = runtime();
        let queue = unsafe { &*rt }.queue.clone();
        let w = Box::into_raw(Box::new(TpbWorker {
            worker: Arc::new(RwLock::new(None)),
            shutdown_initiated: AtomicBool::new(false),
            queue: queue.clone(),
        }));
        unsafe {
            tpb_poll_workflow_activation(w, 1);
            tpb_complete_activity_task(w, 2, std::ptr::null(), 0);
            tpb_worker_finalize_shutdown(w, 3);
            assert_eq!(tpb_worker_initiate_shutdown(w), CALL_FAILED);
            assert_eq!(
                tpb_record_activity_heartbeat(w, std::ptr::null(), 0),
                CALL_FAILED
            );
        }
        queue.spawn(4, KIND_ACTIVITY_TASK, error_status, async {
            panic!("boom")
        });

        let finalized = FINALIZED.as_bytes().to_vec();
        assert_eq!(
            events(rt, 4),
            vec![
                (1, KIND_WORKFLOW_ACTIVATION, STATUS_ERROR, finalized.clone()),
                (2, KIND_ACTIVITY_COMPLETED, STATUS_ERROR, finalized.clone()),
                (3, KIND_SHUTDOWN_FINALIZED, STATUS_ERROR, finalized),
                (
                    4,
                    KIND_ACTIVITY_TASK,
                    STATUS_ERROR,
                    b"Panic in temporal-php-bridge: boom".to_vec()
                ),
            ]
        );
        unsafe {
            tpb_worker_free(w);
            free(rt);
        }
    }
}
