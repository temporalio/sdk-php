use crate::config::{RuntimeJson, parse};
use crate::ffi::{construct, slice};
use crate::queue::Queue;
use std::sync::Arc;
use temporalio_common::telemetry::{CoreLog, CoreLogConsumer, Logger, TelemetryOptions};
use temporalio_sdk_core::{CoreRuntime, RuntimeOptions, TokioRuntimeBuilder};

pub struct TpbRuntime {
    pub core: CoreRuntime,
    pub queue: Arc<Queue>,
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

fn new_runtime(config: &[u8]) -> Result<TpbRuntime, String> {
    let config: RuntimeJson = parse(config, "runtime config")?;
    let telemetry = match config.log {
        Some(filter) => TelemetryOptions::builder()
            .logging(Logger::Push {
                filter,
                consumer: Arc::new(StderrLog),
            })
            .build(),
        None => TelemetryOptions::default(),
    };
    let options = RuntimeOptions::builder()
        .telemetry_options(telemetry)
        .build()?;
    let mut tokio = TokioRuntimeBuilder::default();
    tokio.inner.worker_threads(config.threads);
    let core = CoreRuntime::new(options, tokio).map_err(|e| e.to_string())?;
    let queue = Arc::new(Queue::new(core.tokio_handle())?);
    Ok(TpbRuntime { core, queue })
}

#[unsafe(no_mangle)]
pub unsafe extern "C" fn tpb_runtime_new(
    config: *const libc::c_char,
    config_len: usize,
    err: *mut *mut u8,
    err_len: *mut usize,
) -> *mut TpbRuntime {
    unsafe { construct(err, err_len, || new_runtime(slice(config, config_len))) }
}
