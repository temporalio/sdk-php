#![allow(clippy::not_unsafe_ptr_arg_deref)]

use prost::Message;
use serde_json::Value;
use std::{
    collections::VecDeque,
    os::fd::RawFd,
    sync::{
        Arc, Condvar, Mutex,
        atomic::{AtomicBool, Ordering},
    },
    time::Duration,
};
use temporalio_client::{ClientTlsOptions, Connection, ConnectionOptions, TlsOptions};
use temporalio_common::{
    protos::coresdk::{
        ActivityHeartbeat, ActivityTaskCompletion,
        workflow_completion::WorkflowActivationCompletion,
    },
    protos::temporal::api::enums::v1::VersioningBehavior,
    protos::temporal::api::history::v1::History,
    telemetry::{CoreLog, CoreLogConsumer, Logger, TelemetryOptions},
    worker::{WorkerDeploymentOptions, WorkerDeploymentVersion, WorkerTaskTypes},
};
use temporalio_sdk_core::{
    CoreRuntime, PollError, PollerBehavior, RuntimeOptions, TokioRuntimeBuilder, Url, Worker,
    WorkerConfig, WorkerVersioningStrategy,
    replay::{HistoryForReplay, ReplayWorkerInput},
};
use tokio::runtime::Handle;

const KIND_WORKFLOW_ACTIVATION: i32 = 1;
const KIND_ACTIVITY_TASK: i32 = 2;
const KIND_WORKFLOW_COMPLETED: i32 = 3;
const KIND_ACTIVITY_COMPLETED: i32 = 4;
const KIND_SHUTDOWN_FINALIZED: i32 = 5;

const STATUS_OK: i32 = 0;
const STATUS_ERROR: i32 = 1;
const STATUS_SHUTDOWN: i32 = 2;

#[repr(C)]
pub struct TpbEvent {
    pub tag: u64,
    pub kind: i32,
    pub status: i32,
    pub data: *mut u8,
    pub len: usize,
}

struct Queue {
    events: Mutex<VecDeque<TpbEvent>>,
    ready: Condvar,
    read_fd: RawFd,
    write_fd: RawFd,
    fd_watched: AtomicBool,
}

unsafe impl Send for Queue {}
unsafe impl Sync for Queue {}

impl Queue {
    fn push(&self, tag: u64, kind: i32, status: i32, bytes: Vec<u8>) {
        let (data, len) = into_raw_bytes(bytes);
        self.events.lock().unwrap().push_back(TpbEvent {
            tag,
            kind,
            status,
            data,
            len,
        });
        self.ready.notify_one();
        if self.fd_watched.load(Ordering::Relaxed) {
            unsafe { libc::write(self.write_fd, [1u8].as_ptr().cast(), 1) };
        }
    }

    fn push_error(&self, tag: u64, kind: i32, result: Result<(), String>) {
        if let Err(message) = result {
            self.push(tag, kind, STATUS_ERROR, message.into_bytes());
        }
    }

    fn push_result(&self, tag: u64, kind: i32, result: Result<Vec<u8>, String>) {
        match result {
            Ok(bytes) => self.push(tag, kind, STATUS_OK, bytes),
            Err(message) => self.push(tag, kind, STATUS_ERROR, message.into_bytes()),
        }
    }

    fn push_poll<T: Message>(&self, tag: u64, kind: i32, result: Result<T, PollError>) {
        match result {
            Ok(message) => self.push(tag, kind, STATUS_OK, message.encode_to_vec()),
            Err(PollError::ShutDown) => self.push(tag, kind, STATUS_SHUTDOWN, Vec::new()),
            Err(err) => self.push(tag, kind, STATUS_ERROR, err.to_string().into_bytes()),
        }
    }
}

impl Drop for Queue {
    fn drop(&mut self) {
        for event in self.events.get_mut().unwrap().drain(..) {
            tpb_bytes_free(event.data, event.len);
        }
        unsafe {
            libc::close(self.read_fd);
            libc::close(self.write_fd);
        }
    }
}

fn into_raw_bytes(bytes: Vec<u8>) -> (*mut u8, usize) {
    if bytes.is_empty() {
        return (std::ptr::null_mut(), 0);
    }
    let len = bytes.len();
    (Box::into_raw(bytes.into_boxed_slice()).cast(), len)
}

fn slice<'a>(data: *const libc::c_char, len: usize) -> &'a [u8] {
    if data.is_null() || len == 0 {
        return &[];
    }
    unsafe { std::slice::from_raw_parts(data.cast(), len) }
}

#[derive(Debug)]
struct StderrLog;

impl CoreLogConsumer for StderrLog {
    fn on_log(&self, log: CoreLog) {
        eprintln!(
            "[temporal-core] {} {}: {} {:?}",
            log.level, log.target, log.message, log.fields
        );
    }
}

pub struct TpbRuntime {
    core: CoreRuntime,
    queue: Arc<Queue>,
}

pub struct TpbWorker {
    worker: Option<Arc<Worker>>,
    handle: Handle,
    queue: Arc<Queue>,
}

impl TpbWorker {
    fn core(&self) -> Arc<Worker> {
        self.worker.clone().expect("worker is already finalized")
    }
}

fn new_runtime() -> Result<TpbRuntime, String> {
    let telemetry = match std::env::var("TEMPORAL_CORE_LOG") {
        Ok(filter) => TelemetryOptions::builder()
            .logging(Logger::Push {
                filter,
                consumer: Arc::new(StderrLog),
            })
            .build(),
        Err(_) => TelemetryOptions::default(),
    };
    let options = RuntimeOptions::builder()
        .telemetry_options(telemetry)
        .build()?;
    let mut tokio = TokioRuntimeBuilder::default();
    tokio.inner.worker_threads(
        std::env::var("TEMPORAL_CORE_THREADS")
            .ok()
            .and_then(|v| v.parse::<usize>().ok())
            .unwrap_or(1),
    );
    let core = CoreRuntime::new(options, tokio).map_err(|e| e.to_string())?;
    let mut fds: [RawFd; 2] = [0; 2];
    if unsafe { libc::pipe(fds.as_mut_ptr()) } != 0 {
        return Err(std::io::Error::last_os_error().to_string());
    }
    for fd in fds {
        unsafe {
            libc::fcntl(
                fd,
                libc::F_SETFL,
                libc::fcntl(fd, libc::F_GETFL) | libc::O_NONBLOCK,
            );
            libc::fcntl(fd, libc::F_SETFD, libc::FD_CLOEXEC);
        }
    }
    let queue = Arc::new(Queue {
        events: Mutex::new(VecDeque::new()),
        ready: Condvar::new(),
        read_fd: fds[0],
        write_fd: fds[1],
        fd_watched: AtomicBool::new(false),
    });
    Ok(TpbRuntime { core, queue })
}

#[unsafe(no_mangle)]
pub extern "C" fn tpb_runtime_new() -> *mut TpbRuntime {
    match new_runtime() {
        Ok(runtime) => Box::into_raw(Box::new(runtime)),
        Err(err) => {
            eprintln!("tpb_runtime_new: {err}");
            std::ptr::null_mut()
        }
    }
}

#[unsafe(no_mangle)]
pub extern "C" fn tpb_runtime_free(rt: *mut TpbRuntime) {
    if !rt.is_null() {
        drop(unsafe { Box::from_raw(rt) });
    }
}

#[unsafe(no_mangle)]
pub extern "C" fn tpb_event_fd(rt: *mut TpbRuntime) -> libc::c_int {
    let queue = &unsafe { &*rt }.queue;
    queue.fd_watched.store(true, Ordering::Relaxed);
    queue.read_fd
}

#[unsafe(no_mangle)]
pub extern "C" fn tpb_next_events(
    rt: *mut TpbRuntime,
    timeout_ms: i32,
    out: *mut TpbEvent,
    max: usize,
) -> usize {
    let queue = &unsafe { &*rt }.queue;
    let mut events = queue.events.lock().unwrap();
    if events.is_empty() && timeout_ms != 0 {
        events = if timeout_ms < 0 {
            queue.ready.wait_while(events, |e| e.is_empty()).unwrap()
        } else {
            let timeout = Duration::from_millis(timeout_ms as u64);
            queue
                .ready
                .wait_timeout_while(events, timeout, |e| e.is_empty())
                .unwrap()
                .0
        };
    }
    let count = events.len().min(max);
    for (i, event) in events.drain(..count).enumerate() {
        unsafe { out.add(i).write(event) };
    }
    count
}

#[unsafe(no_mangle)]
pub extern "C" fn tpb_bytes_free(data: *mut u8, len: usize) {
    if !data.is_null() && len > 0 {
        drop(unsafe { Box::from_raw(std::ptr::slice_from_raw_parts_mut(data, len)) });
    }
}

fn worker_config(config: &Value) -> Result<WorkerConfig, String> {
    let text = |key: &str, default: &str| {
        config
            .get(key)
            .and_then(Value::as_str)
            .unwrap_or(default)
            .to_owned()
    };
    let number =
        |key: &str, default: u64| config.get(key).and_then(Value::as_u64).unwrap_or(default);
    let flag =
        |key: &str, default: bool| config.get(key).and_then(Value::as_bool).unwrap_or(default);
    let workflows = flag("workflows", true);
    let activities = flag("activities", true);
    WorkerConfig::builder()
        .namespace(text("namespace", "default"))
        .task_queue(text("task_queue", ""))
        .versioning_strategy(versioning_strategy(config)?)
        .max_cached_workflows(number("max_cached_workflows", 1000) as usize)
        .max_outstanding_workflow_tasks(number("max_outstanding_workflow_tasks", 100) as usize)
        .max_outstanding_activities(number("max_outstanding_activities", 100) as usize)
        .max_outstanding_local_activities(number("max_outstanding_local_activities", 100) as usize)
        .workflow_task_poller_behavior(PollerBehavior::SimpleMaximum(number(
            "max_concurrent_workflow_task_polls",
            5,
        ) as usize))
        .activity_task_poller_behavior(PollerBehavior::SimpleMaximum(number(
            "max_concurrent_activity_task_polls",
            5,
        ) as usize))
        .nonsticky_to_sticky_poll_ratio(
            config
                .get("nonsticky_to_sticky_poll_ratio")
                .and_then(Value::as_f64)
                .unwrap_or(0.2) as f32,
        )
        .sticky_queue_schedule_to_start_timeout(Duration::from_millis(number(
            "sticky_queue_schedule_to_start_timeout_ms",
            10_000,
        )))
        .graceful_shutdown_period(Duration::ZERO)
        .task_types(WorkerTaskTypes {
            enable_workflows: workflows,
            enable_local_activities: workflows && activities,
            enable_remote_activities: activities && !flag("no_remote_activities", false),
            enable_nexus: false,
        })
        .build()
}

fn versioning_strategy(config: &Value) -> Result<WorkerVersioningStrategy, String> {
    let deployment = config.get("deployment");
    let Some(version) = deployment
        .and_then(|d| d.get("Version"))
        .filter(|v| !v.is_null())
    else {
        return Ok(WorkerVersioningStrategy::None {
            build_id: config
                .get("build_id")
                .and_then(Value::as_str)
                .unwrap_or_default()
                .to_owned(),
        });
    };
    let text = |key: &str| {
        version
            .get(key)
            .and_then(Value::as_str)
            .unwrap_or_default()
            .to_owned()
    };
    let default_behavior = deployment
        .and_then(|d| d.get("DefaultVersioningBehavior"))
        .and_then(Value::as_i64)
        .unwrap_or(0) as i32;
    Ok(WorkerVersioningStrategy::WorkerDeploymentBased(
        WorkerDeploymentOptions {
            version: WorkerDeploymentVersion {
                deployment_name: text("DeploymentName"),
                build_id: text("BuildId"),
            },
            use_worker_versioning: deployment
                .and_then(|d| d.get("UseVersioning"))
                .and_then(Value::as_bool)
                .unwrap_or(false),
            default_versioning_behavior: match default_behavior {
                0 => None,
                v => Some(VersioningBehavior::try_from(v).map_err(|e| e.to_string())?),
            },
        },
    ))
}

fn tls_options(tls: &Value) -> Option<TlsOptions> {
    let tls = tls.as_object()?;
    let text = |key: &str| tls.get(key).and_then(Value::as_str).map(str::to_owned);
    let bytes = |key: &str| text(key).map(String::into_bytes);
    Some(TlsOptions {
        server_root_ca_cert: bytes("server_root_ca_cert"),
        domain: text("domain"),
        client_tls_options: bytes("client_cert").zip(bytes("client_private_key")).map(
            |(client_cert, client_private_key)| ClientTlsOptions {
                client_cert,
                client_private_key,
            },
        ),
    })
}

fn new_worker(rt: &TpbRuntime, config: &[u8]) -> Result<TpbWorker, String> {
    let config: Value =
        serde_json::from_slice(config).map_err(|e| format!("Invalid config JSON: {e}"))?;
    let worker_config = worker_config(&config)?;
    let target = config
        .get("target_url")
        .and_then(Value::as_str)
        .unwrap_or("http://127.0.0.1:7233");
    let identity = config
        .get("identity")
        .and_then(Value::as_str)
        .map(str::to_owned)
        .unwrap_or_else(|| format!("{}@temporal-php", std::process::id()));
    let text = |key: &str| config.get(key).and_then(Value::as_str).map(str::to_owned);
    let options = ConnectionOptions::new(Url::parse(target).map_err(|e| e.to_string())?)
        .client_name(text("client_name").unwrap_or_default())
        .client_version(text("client_version").unwrap_or_default())
        .identity(identity)
        .maybe_api_key(text("api_key"))
        .maybe_tls_options(config.get("tls").and_then(tls_options))
        .build();
    let handle = rt.core.tokio_handle();
    let worker = handle.block_on(async {
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
    Ok(TpbWorker {
        worker: Some(Arc::new(worker)),
        handle,
        queue: rt.queue.clone(),
    })
}

fn new_replayer(rt: &TpbRuntime, config: &[u8], history: &[u8]) -> Result<TpbWorker, String> {
    let config: Value =
        serde_json::from_slice(config).map_err(|e| format!("Invalid config JSON: {e}"))?;
    let history = History::decode(history).map_err(|e| format!("Invalid history: {e}"))?;
    let workflow_id = config
        .get("workflow_id")
        .and_then(Value::as_str)
        .unwrap_or_default();
    let input = ReplayWorkerInput::new(
        worker_config(&config)?,
        futures_util::stream::iter([HistoryForReplay::new(history, workflow_id)]),
    );
    let handle = rt.core.tokio_handle();
    let worker = {
        let _guard = handle.enter();
        temporalio_sdk_core::init_replay_worker(input)
            .map_err(|e| format!("Replay worker start failed: {e}"))?
    };
    Ok(TpbWorker {
        worker: Some(Arc::new(worker)),
        handle,
        queue: rt.queue.clone(),
    })
}

#[unsafe(no_mangle)]
pub extern "C" fn tpb_worker_new(
    rt: *mut TpbRuntime,
    config: *const libc::c_char,
    config_len: usize,
    err: *mut *mut u8,
    err_len: *mut usize,
) -> *mut TpbWorker {
    worker_or_error(
        new_worker(unsafe { &*rt }, slice(config, config_len)),
        err,
        err_len,
    )
}

#[unsafe(no_mangle)]
pub extern "C" fn tpb_replayer_new(
    rt: *mut TpbRuntime,
    config: *const libc::c_char,
    config_len: usize,
    history: *const libc::c_char,
    history_len: usize,
    err: *mut *mut u8,
    err_len: *mut usize,
) -> *mut TpbWorker {
    worker_or_error(
        new_replayer(
            unsafe { &*rt },
            slice(config, config_len),
            slice(history, history_len),
        ),
        err,
        err_len,
    )
}

fn worker_or_error(
    result: Result<TpbWorker, String>,
    err: *mut *mut u8,
    err_len: *mut usize,
) -> *mut TpbWorker {
    match result {
        Ok(worker) => Box::into_raw(Box::new(worker)),
        Err(message) => {
            let (data, len) = into_raw_bytes(message.into_bytes());
            unsafe {
                if !err.is_null() {
                    *err = data;
                }
                if !err_len.is_null() {
                    *err_len = len;
                }
            }
            std::ptr::null_mut()
        }
    }
}

#[unsafe(no_mangle)]
pub extern "C" fn tpb_poll_workflow_activation(w: *mut TpbWorker, tag: u64) {
    let w = unsafe { &*w };
    let (core, queue) = (w.core(), w.queue.clone());
    w.handle.spawn(async move {
        let result = core.poll_workflow_activation().await;
        drop(core);
        queue.push_poll(tag, KIND_WORKFLOW_ACTIVATION, result);
    });
}

#[unsafe(no_mangle)]
pub extern "C" fn tpb_poll_activity_task(w: *mut TpbWorker, tag: u64) {
    let w = unsafe { &*w };
    let (core, queue) = (w.core(), w.queue.clone());
    w.handle.spawn(async move {
        let result = core.poll_activity_task().await;
        drop(core);
        queue.push_poll(tag, KIND_ACTIVITY_TASK, result);
    });
}

#[unsafe(no_mangle)]
pub extern "C" fn tpb_complete_workflow_activation(
    w: *mut TpbWorker,
    tag: u64,
    data: *const libc::c_char,
    len: usize,
) {
    let w = unsafe { &*w };
    let completion = match WorkflowActivationCompletion::decode(slice(data, len)) {
        Ok(completion) => completion,
        Err(e) => {
            return w.queue.push_result(
                tag,
                KIND_WORKFLOW_COMPLETED,
                Err(format!("Decode failure: {e}")),
            );
        }
    };
    let (core, queue) = (w.core(), w.queue.clone());
    w.handle.spawn(async move {
        let result = core.complete_workflow_activation(completion).await;
        drop(core);
        queue.push_error(
            tag,
            KIND_WORKFLOW_COMPLETED,
            result.map_err(|e| e.to_string()),
        );
    });
}

#[unsafe(no_mangle)]
pub extern "C" fn tpb_complete_activity_task(
    w: *mut TpbWorker,
    tag: u64,
    data: *const libc::c_char,
    len: usize,
) {
    let w = unsafe { &*w };
    let completion = match ActivityTaskCompletion::decode(slice(data, len)) {
        Ok(completion) => completion,
        Err(e) => {
            return w.queue.push_result(
                tag,
                KIND_ACTIVITY_COMPLETED,
                Err(format!("Decode failure: {e}")),
            );
        }
    };
    let (core, queue) = (w.core(), w.queue.clone());
    w.handle.spawn(async move {
        let result = core.complete_activity_task(completion).await;
        drop(core);
        queue.push_error(
            tag,
            KIND_ACTIVITY_COMPLETED,
            result.map_err(|e| e.to_string()),
        );
    });
}

#[unsafe(no_mangle)]
pub extern "C" fn tpb_record_activity_heartbeat(
    w: *mut TpbWorker,
    data: *const libc::c_char,
    len: usize,
) -> i32 {
    let w = unsafe { &*w };
    let Ok(heartbeat) = ActivityHeartbeat::decode(slice(data, len)) else {
        return 1;
    };
    let _guard = w.handle.enter();
    w.core().record_activity_heartbeat(heartbeat);
    0
}

#[unsafe(no_mangle)]
pub extern "C" fn tpb_request_workflow_eviction(
    w: *mut TpbWorker,
    run_id: *const libc::c_char,
    len: usize,
) {
    let w = unsafe { &*w };
    let _guard = w.handle.enter();
    w.core()
        .request_workflow_eviction(&String::from_utf8_lossy(slice(run_id, len)));
}

#[unsafe(no_mangle)]
pub extern "C" fn tpb_worker_initiate_shutdown(w: *mut TpbWorker) {
    let w = unsafe { &*w };
    let _guard = w.handle.enter();
    w.core().initiate_shutdown();
}

#[unsafe(no_mangle)]
pub extern "C" fn tpb_worker_finalize_shutdown(w: *mut TpbWorker, tag: u64) {
    let w = unsafe { &mut *w };
    let Some(core) = w.worker.take() else {
        return w.queue.push_result(
            tag,
            KIND_SHUTDOWN_FINALIZED,
            Err("Worker is already finalized".into()),
        );
    };
    let queue = w.queue.clone();
    w.handle.spawn(async move {
        let result = match Arc::try_unwrap(core) {
            Ok(core) => {
                core.finalize_shutdown().await;
                Ok(Vec::new())
            }
            Err(core) => Err(format!(
                "Cannot finalize, {} references are alive, wait for all polls and completions first",
                Arc::strong_count(&core)
            )),
        };
        queue.push_result(tag, KIND_SHUTDOWN_FINALIZED, result);
    });
}

#[unsafe(no_mangle)]
pub extern "C" fn tpb_worker_free(w: *mut TpbWorker) {
    if !w.is_null() {
        drop(unsafe { Box::from_raw(w) });
    }
}
