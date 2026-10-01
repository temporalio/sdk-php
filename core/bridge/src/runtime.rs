use crate::config::{RuntimeJson, parse};
use crate::ffi::{guard, into_ffi, slice};
use crate::queue::Queue;
use std::sync::Arc;
use temporalio_common::telemetry::{Logger, LoggerFormat, TelemetryOptions};
use temporalio_sdk_core::{CoreRuntime, RuntimeOptions, TokioRuntimeBuilder};

pub struct TpbRuntime {
    pub core: CoreRuntime,
    pub queue: Arc<Queue>,
}

fn new_runtime(config: &[u8]) -> Result<TpbRuntime, String> {
    let config: RuntimeJson = parse(config, "runtime config")?;
    let telemetry = match config.log {
        Some(filter) => TelemetryOptions::builder()
            .logging(Logger::Console {
                filter,
                format: Some(LoggerFormat::Compact),
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
    guard(
        |message| unsafe { into_ffi(Err(message), err, err_len) },
        || unsafe { into_ffi(new_runtime(slice(config, config_len)), err, err_len) },
    )
}
