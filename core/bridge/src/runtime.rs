use crate::config::{RuntimeJson, parse};
use crate::ffi::{construct, slice};
use crate::queue::Queue;
use std::net::SocketAddr;
use std::sync::Arc;
use temporalio_common::telemetry::{
    CoreLog, CoreLogConsumer, Logger, PrometheusExporterOptions, TelemetryOptions,
    metrics::CoreMeter, start_prometheus_metric_exporter,
};
use temporalio_sdk_core::{CoreRuntime, RuntimeOptions, TokioRuntimeBuilder};

const PROMETHEUS_PORT_ATTEMPTS: u16 = 64;

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

fn prometheus_meter(address: &str) -> Result<Arc<dyn CoreMeter>, String> {
    let base: SocketAddr = address
        .parse()
        .map_err(|e| format!("Invalid Prometheus address {address}: {e}"))?;
    let mut last_error = String::new();
    for offset in 0..PROMETHEUS_PORT_ATTEMPTS {
        let Some(port) = base.port().checked_add(offset) else {
            break;
        };
        let mut socket_addr = base;
        socket_addr.set_port(port);
        match start_prometheus_metric_exporter(
            PrometheusExporterOptions::builder()
                .socket_addr(socket_addr)
                .build(),
        ) {
            Ok(server) => {
                eprintln!("[temporal-core] Prometheus metrics on http://{socket_addr}/metrics");
                return Ok(server.meter);
            }
            Err(e) => last_error = e.to_string(),
        }
    }
    Err(format!(
        "No free port for the Prometheus exporter from {base}: {last_error}"
    ))
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
    let mut core = CoreRuntime::new(options, tokio).map_err(|e| e.to_string())?;
    if let Some(address) = config.prometheus {
        let _guard = core.tokio_handle().enter();
        let meter = prometheus_meter(&address)?;
        core.telemetry_mut().attach_late_init_metrics(meter);
    }
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

#[cfg(test)]
mod tests {
    use super::*;
    use std::io::{Read, Write};
    use std::net::{TcpListener, TcpStream};

    fn scrape(port: u16) -> String {
        let mut stream = TcpStream::connect(("127.0.0.1", port)).unwrap();
        stream
            .write_all(b"GET /metrics HTTP/1.1\r\nHost: localhost\r\nConnection: close\r\n\r\n")
            .unwrap();
        let mut response = String::new();
        stream.read_to_string(&mut response).unwrap();
        response
    }

    #[test]
    fn prometheus_exporter_moves_to_the_next_free_port() {
        let taken = TcpListener::bind("127.0.0.1:0").unwrap();
        let port = taken.local_addr().unwrap().port();
        let config = format!(r#"{{"threads":1,"log":null,"prometheus":"127.0.0.1:{port}"}}"#);

        let runtime = new_runtime(config.as_bytes()).unwrap();

        assert!(scrape(port + 1).starts_with("HTTP/1.1 200"));
        drop(runtime);
    }

    #[test]
    fn invalid_prometheus_address_is_an_error() {
        let config = br#"{"threads":1,"log":null,"prometheus":"not an address"}"#;

        assert!(new_runtime(config).is_err_and(|e| e.contains("Invalid Prometheus address")));
    }
}
