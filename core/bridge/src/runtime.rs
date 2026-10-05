use crate::config::{RuntimeJson, parse};
use crate::ffi::{KIND_LOG, STATUS_OK, bytes, construct};
use crate::queue::Queue;
use serde_json::Value;
use std::collections::HashMap;
use std::net::SocketAddr;
use std::sync::{Arc, Mutex, OnceLock};
use temporalio_client::Connection;
use temporalio_common::telemetry::{
    CoreLog, CoreLogConsumer, Logger, PrometheusExporterOptions, TelemetryOptions,
    metrics::CoreMeter, start_prometheus_metric_exporter,
};
use temporalio_sdk_core::{CoreRuntime, RuntimeOptions, TokioRuntimeBuilder};

const PROMETHEUS_PORT_ATTEMPTS: u16 = 64;

pub struct TpbRuntime {
    pub core: CoreRuntime,
    pub queue: Arc<Queue>,
    pub connections: Mutex<HashMap<String, Connection>>,
}

struct QueueLog(Arc<OnceLock<Arc<Queue>>>);

impl std::fmt::Debug for QueueLog {
    fn fmt(&self, f: &mut std::fmt::Formatter<'_>) -> std::fmt::Result {
        f.write_str("QueueLog")
    }
}

fn push_log(
    queue: &Queue,
    level: &str,
    target: &str,
    message: &str,
    fields: &HashMap<String, Value>,
) {
    let entry = serde_json::json!({
        "level": level,
        "target": target,
        "message": message,
        "fields": fields,
    });
    queue.push(0, KIND_LOG, STATUS_OK, entry.to_string().into_bytes());
}

impl CoreLogConsumer for QueueLog {
    fn on_log(&self, log: CoreLog) {
        if let Some(queue) = self.0.get() {
            push_log(
                queue,
                log.level.as_str(),
                &log.target,
                &log.message,
                &log.fields,
            );
        }
    }
}

fn prometheus_meter(address: &str, queue: &Queue) -> Result<Arc<dyn CoreMeter>, String> {
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
                let message = format!("Prometheus metrics on http://{socket_addr}/metrics");
                push_log(
                    queue,
                    "INFO",
                    env!("CARGO_CRATE_NAME"),
                    &message,
                    &HashMap::new(),
                );
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
    let log_queue = Arc::new(OnceLock::new());
    let telemetry = TelemetryOptions::builder()
        .logging(Logger::Push {
            filter: config.log,
            consumer: Arc::new(QueueLog(log_queue.clone())),
        })
        .build();
    let options = RuntimeOptions::builder()
        .telemetry_options(telemetry)
        .build()?;
    let mut tokio = TokioRuntimeBuilder::default();
    tokio.inner.worker_threads(config.threads);
    let mut core = CoreRuntime::new(options, tokio).map_err(|e| e.to_string())?;
    let queue = Arc::new(Queue::new(core.tokio_handle())?);
    let _ = log_queue.set(queue.clone());
    if let Some(address) = config.prometheus {
        let _guard = core.tokio_handle().enter();
        let meter = prometheus_meter(&address, &queue)?;
        core.telemetry_mut().attach_late_init_metrics(meter);
    }
    Ok(TpbRuntime {
        core,
        queue,
        connections: Mutex::new(HashMap::new()),
    })
}

#[unsafe(no_mangle)]
pub unsafe extern "C" fn tpb_runtime_new(
    config: *const libc::c_char,
    config_len: usize,
    err: *mut *mut u8,
    err_len: *mut usize,
) -> *mut TpbRuntime {
    construct(err, err_len, || new_runtime(bytes(config, config_len)))
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::ffi::tpb_bytes_free;
    use crate::testing::events;
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

    fn log_entry(runtime: &TpbRuntime) -> Value {
        let rt = std::ptr::from_ref(runtime).cast_mut();
        let [(tag, kind, status, data)] = events(rt, 1).try_into().unwrap();
        assert_eq!((tag, kind, status), (0, KIND_LOG, STATUS_OK));
        serde_json::from_slice(&data).unwrap()
    }

    #[test]
    fn log_consumer_debug_output_has_no_queue_contents() {
        assert_eq!(
            format!("{:?}", QueueLog(Arc::new(OnceLock::new()))),
            "QueueLog"
        );
    }

    #[test]
    fn prometheus_exporter_moves_to_the_next_free_port_and_logs_it() {
        let taken = TcpListener::bind("127.0.0.1:0").unwrap();
        let port = taken.local_addr().unwrap().port();
        let config = format!(r#"{{"threads":1,"log":"off","prometheus":"127.0.0.1:{port}"}}"#);

        let runtime = new_runtime(config.as_bytes()).unwrap();

        let entry = log_entry(&runtime);
        assert_eq!(entry["level"], "INFO");
        assert_eq!(entry["target"], "temporal_php_bridge");
        assert_eq!(entry["fields"], serde_json::json!({}));
        let exporter_port: u16 = entry["message"]
            .as_str()
            .unwrap()
            .strip_prefix("Prometheus metrics on http://127.0.0.1:")
            .and_then(|rest| rest.strip_suffix("/metrics"))
            .unwrap()
            .parse()
            .unwrap();
        assert!(exporter_port > port);
        assert!(scrape(exporter_port).starts_with("HTTP/1.1 200"));
    }

    #[test]
    fn prometheus_exporter_stops_at_the_last_port() {
        let _taken = TcpListener::bind("127.0.0.1:65535");
        let config = br#"{"threads":1,"log":"off","prometheus":"127.0.0.1:65535"}"#;

        assert!(new_runtime(config).is_err_and(|e| {
            e.starts_with("No free port for the Prometheus exporter from 127.0.0.1:65535: ")
        }));
    }

    #[test]
    fn core_logs_are_queued_as_log_events() {
        let runtime = new_runtime(br#"{"threads":1,"log":"warn"}"#).unwrap();

        runtime.core.tokio_handle().block_on(async {
            tracing::warn!(target: "temporalio_sdk_core", answer = 42, "core warning");
        });

        let entry = log_entry(&runtime);
        assert_eq!(entry["level"], "WARN");
        assert_eq!(entry["target"], "temporalio_sdk_core");
        assert_eq!(entry["message"], "core warning");
        assert_eq!(entry["fields"]["answer"], 42);
    }

    #[test]
    fn invalid_prometheus_address_is_an_error() {
        let config = br#"{"threads":1,"log":"off","prometheus":"not an address"}"#;

        assert!(new_runtime(config).is_err_and(|e| e.contains("Invalid Prometheus address")));
    }

    #[test]
    fn invalid_runtime_config_is_returned_through_the_error_pointers() {
        let (mut err, mut err_len) = (std::ptr::null_mut(), 0);
        let config = b"{";

        let rt = unsafe { tpb_runtime_new(config.as_ptr().cast(), 1, &mut err, &mut err_len) };

        assert!(rt.is_null());
        let message = bytes(err.cast(), err_len);
        assert!(message.starts_with(b"Invalid runtime config JSON: "));
        unsafe { tpb_bytes_free(err, err_len) };
    }
}
