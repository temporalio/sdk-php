use crate::config::{RuntimeJson, parse};
use crate::ffi::{bytes, construct};
use crate::queue::Queue;
use std::collections::HashMap;
use std::net::SocketAddr;
use std::sync::{Arc, OnceLock};
use std::time::Duration;
use temporalio_client::Connection;
use temporalio_common::telemetry::{
    CoreLog, CoreLogConsumer, Logger, PrometheusExporterOptions, TelemetryOptions,
    build_otlp_metric_exporter, metrics::CoreMeter, start_prometheus_metric_exporter,
};
use temporalio_sdk_core::{CoreRuntime, RuntimeOptions, TokioRuntimeBuilder};
use tokio::sync::Mutex;

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

impl CoreLogConsumer for QueueLog {
    fn on_log(&self, log: CoreLog) {
        if let Some(queue) = self.0.get() {
            queue.push_log(log.level.as_str(), &log.target, &log.message, &log.fields);
        }
    }
}

fn prometheus_meter(
    address: &str,
    global_tags: Option<HashMap<String, String>>,
    queue: &Queue,
) -> Result<Arc<dyn CoreMeter>, String> {
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
                .maybe_global_tags(global_tags.clone())
                .build(),
        ) {
            Ok(server) => {
                let message = format!("Prometheus metrics on http://{socket_addr}/metrics");
                queue.push_log("INFO", env!("CARGO_CRATE_NAME"), &message, &HashMap::new());
                return Ok(server.meter);
            }
            Err(e)
                if e.downcast_ref::<std::io::Error>()
                    .is_some_and(|e| e.kind() == std::io::ErrorKind::AddrInUse) =>
            {
                last_error = e.to_string()
            }
            Err(e) => {
                return Err(format!(
                    "Unable to start the Prometheus exporter on {socket_addr}: {e}"
                ));
            }
        }
    }
    Err(format!(
        "No free port for the Prometheus exporter from {base}: {last_error}"
    ))
}

fn new_runtime(config: &[u8]) -> Result<TpbRuntime, String> {
    let config: RuntimeJson = parse(config, "runtime config")?;
    if config.prometheus.is_some() && config.otel.is_some() {
        return Err("Prometheus and OpenTelemetry metrics cannot be used together".into());
    }
    let log_queue = Arc::new(OnceLock::new());
    let telemetry = TelemetryOptions::builder()
        .logging(Logger::Push {
            filter: config.log,
            consumer: Arc::new(QueueLog(log_queue.clone())),
        })
        .maybe_metric_prefix(config.metric_prefix)
        .build();
    let options = RuntimeOptions::builder()
        .telemetry_options(telemetry)
        .heartbeat_interval(
            config
                .worker_heartbeat_interval_ms
                .map(Duration::from_millis),
        )
        .build()?;
    let mut tokio = TokioRuntimeBuilder::default();
    tokio.inner.worker_threads(config.threads.get());
    let mut core = CoreRuntime::new(options, tokio).map_err(|e| e.to_string())?;
    let queue = Arc::new(Queue::new(core.tokio_handle()));
    let _ = log_queue.set(queue.clone());
    let meter = {
        let _guard = core.tokio_handle().enter();
        match (config.prometheus, config.otel) {
            (Some(address), _) => Some(prometheus_meter(&address, config.global_tags, &queue)?),
            (None, Some(otel)) => Some(Arc::new(
                build_otlp_metric_exporter(otel.options(config.global_tags)?)
                    .map_err(|e| format!("Unable to start the OpenTelemetry exporter: {e}"))?,
            ) as Arc<dyn CoreMeter>),
            (None, None) => None,
        }
    };
    if let Some(meter) = meter {
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
    use crate::ffi::{KIND_ACTIVITY_TASK, KIND_LOG, STATUS_OK};
    use crate::queue::MAX_PENDING_LOGS;
    use crate::testing::{
        Checked, Event, drain_events, events, grpc_server, only, start_worker, worker_json,
    };
    use crate::worker::tpb_worker_free;
    use serde_json::Value;
    use std::io::{BufRead, BufReader, Read, Write};
    use std::net::{TcpListener, TcpStream};

    fn scrape(port: u16) -> std::io::Result<String> {
        let mut stream = TcpStream::connect(("127.0.0.1", port))?;
        stream
            .write_all(b"GET /metrics HTTP/1.1\r\nHost: localhost\r\nConnection: close\r\n\r\n")?;
        let mut response = String::new();
        stream.read_to_string(&mut response)?;
        Ok(response)
    }

    fn log_entry(runtime: &TpbRuntime) -> Result<Value, Box<dyn std::error::Error>> {
        let rt = std::ptr::from_ref(runtime).cast_mut();
        let [(tag, kind, status, data)] = only(events(rt, 1))?;
        assert_eq!((tag, kind, status), (0, KIND_LOG, STATUS_OK));
        Ok(serde_json::from_slice(&data)?)
    }

    fn exporter_port(entry: &Value) -> Result<u16, Box<dyn std::error::Error>> {
        Ok(entry["message"]
            .as_str()
            .and_then(|message| message.strip_prefix("Prometheus metrics on http://127.0.0.1:"))
            .and_then(|rest| rest.strip_suffix("/metrics"))
            .ok_or("no exporter address in the log")?
            .parse()?)
    }

    fn otlp_request(listener: &TcpListener) -> std::io::Result<(Vec<String>, Vec<u8>)> {
        let (stream, _) = listener.accept()?;
        let mut reader = BufReader::new(stream);
        let head: Vec<String> = reader
            .by_ref()
            .lines()
            .map_while(Result::ok)
            .take_while(|line| !line.is_empty())
            .collect();
        let length = head
            .iter()
            .find_map(|line| {
                line.to_ascii_lowercase()
                    .strip_prefix("content-length: ")?
                    .parse()
                    .ok()
            })
            .unwrap_or(0);
        let mut body = vec![0; length];
        reader.read_exact(&mut body)?;
        reader
            .into_inner()
            .write_all(b"HTTP/1.1 200 OK\r\nContent-Length: 0\r\nConnection: close\r\n\r\n")?;
        Ok((head, body))
    }

    fn contains(haystack: &[u8], needle: &[u8]) -> bool {
        haystack.windows(needle.len()).any(|w| w == needle)
    }

    #[test]
    fn otlp_http_exporter_sends_prefixed_and_tagged_worker_metrics() -> Checked {
        let collector = TcpListener::bind("127.0.0.1:0")?;
        let port = collector.local_addr()?.port();
        let config = format!(
            r#"{{"threads":1,"log":"off","metric_prefix":"php_","global_tags":{{"deployment":"otlp-test"}},
            "otel":{{"url":"http://127.0.0.1:{port}/v1/metrics","headers":{{"x-api-key":"secret"}},
            "metric_periodicity_ms":100,"protocol":"http","use_seconds_for_durations":true}}}}"#
        );
        let runtime = new_runtime(config.as_bytes())?;
        let rt = std::ptr::from_ref(&runtime).cast_mut();
        let worker = start_worker(rt, &worker_json(&grpc_server()?))?;

        let received = (0..50).map(|_| otlp_request(&collector)).find(|request| {
            request
                .as_ref()
                .map_or(true, |(_, body)| contains(body, b"php_request"))
        });
        unsafe { tpb_worker_free(worker) };

        let (head, body) = received.ok_or("no php_request metric was exported")??;
        assert_eq!(head[0], "POST /v1/metrics HTTP/1.1");
        assert!(
            head.iter()
                .any(|line| line.eq_ignore_ascii_case("x-api-key: secret")),
            "{head:?}"
        );
        assert!(contains(&body, b"otlp-test"));
        Ok(())
    }

    #[test]
    fn log_consumer_debug_output_has_no_queue_contents() {
        assert_eq!(
            format!("{:?}", QueueLog(Arc::new(OnceLock::new()))),
            "QueueLog"
        );
    }

    #[test]
    fn prometheus_exporter_moves_to_the_next_free_port_and_logs_it() -> Checked {
        let taken = TcpListener::bind("127.0.0.1:0")?;
        let port = taken.local_addr()?.port();
        let config = format!(r#"{{"threads":1,"log":"off","prometheus":"127.0.0.1:{port}"}}"#);

        let runtime = new_runtime(config.as_bytes())?;

        let entry = log_entry(&runtime)?;
        assert_eq!(entry["level"], "INFO");
        assert_eq!(entry["target"], "temporal_php_bridge");
        assert_eq!(entry["fields"], serde_json::json!({}));
        let exporter_port = exporter_port(&entry)?;
        assert!(exporter_port > port);
        assert!(scrape(exporter_port)?.starts_with("HTTP/1.1 200"));
        Ok(())
    }

    #[test]
    fn worker_connection_records_client_request_metrics() -> Checked {
        let taken = TcpListener::bind("127.0.0.1:0")?;
        let port = taken.local_addr()?.port();
        let config = format!(r#"{{"threads":1,"log":"off","prometheus":"127.0.0.1:{port}"}}"#);
        let runtime = new_runtime(config.as_bytes())?;
        let exporter_port = exporter_port(&log_entry(&runtime)?)?;
        let rt = std::ptr::from_ref(&runtime).cast_mut();

        let worker = start_worker(rt, &worker_json(&grpc_server()?))?;

        let metrics = scrape(exporter_port)?;
        unsafe { tpb_worker_free(worker) };
        assert!(
            metrics.contains("temporal_request{"),
            "no client request metric in {metrics}"
        );
        Ok(())
    }

    #[test]
    fn prometheus_metrics_carry_the_metric_prefix_and_the_global_tags() -> Checked {
        let taken = TcpListener::bind("127.0.0.1:0")?;
        let port = taken.local_addr()?.port();
        let config = format!(
            r#"{{"threads":1,"log":"off","prometheus":"127.0.0.1:{port}","metric_prefix":"php_","global_tags":{{"deployment":"prom-test"}}}}"#
        );
        let runtime = new_runtime(config.as_bytes())?;
        let exporter_port = exporter_port(&log_entry(&runtime)?)?;
        let rt = std::ptr::from_ref(&runtime).cast_mut();

        let worker = start_worker(rt, &worker_json(&grpc_server()?))?;

        let metrics = scrape(exporter_port)?;
        unsafe { tpb_worker_free(worker) };
        assert!(
            metrics.lines().any(|line| line.starts_with("php_request{")
                && line.contains(r#"deployment="prom-test""#)),
            "no prefixed and tagged request metric in {metrics}"
        );
        Ok(())
    }

    #[test]
    fn prometheus_and_opentelemetry_metrics_are_alternatives() {
        let config = br#"{"threads":1,"log":"off","prometheus":"127.0.0.1:0","otel":{"url":"http://127.0.0.1:4317","headers":null,"metric_periodicity_ms":1000,"protocol":"grpc","use_seconds_for_durations":false}}"#;

        assert_eq!(
            new_runtime(config).err().as_deref(),
            Some("Prometheus and OpenTelemetry metrics cannot be used together")
        );
    }

    #[test]
    fn invalid_opentelemetry_url_is_an_error() {
        let config = br#"{"threads":1,"log":"off","otel":{"url":"not a url","headers":null,"metric_periodicity_ms":1000,"protocol":"http","use_seconds_for_durations":false}}"#;

        assert_eq!(
            new_runtime(config).err().as_deref(),
            Some("Invalid OpenTelemetry URL not a url: relative URL without a base")
        );
    }

    #[test]
    fn opentelemetry_grpc_exporter_rejects_an_invalid_header() {
        let config = br#"{"threads":1,"log":"off","otel":{"url":"http://127.0.0.1:4317","headers":{"bad header":"x"},"metric_periodicity_ms":1000,"protocol":"grpc","use_seconds_for_durations":false}}"#;

        let error = new_runtime(config).err();

        assert!(
            error
                .as_deref()
                .is_some_and(|e| e.starts_with("Unable to start the OpenTelemetry exporter: ")),
            "{error:?}"
        );
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
    fn prometheus_exporter_stays_on_the_port_when_the_address_is_not_local() {
        let config = br#"{"threads":1,"log":"off","prometheus":"192.0.2.1:9464"}"#;

        let error = new_runtime(config).err();

        assert!(
            error
                .as_deref()
                .is_some_and(|e| e
                    .starts_with("Unable to start the Prometheus exporter on 192.0.2.1:9464: ")),
            "{error:?}"
        );
    }

    #[test]
    fn zero_threads_is_a_config_error() {
        let error = new_runtime(br#"{"threads":0,"log":"off"}"#).err();

        assert!(
            error.as_deref().is_some_and(|e| e.starts_with(
                "Invalid runtime config JSON: invalid value: integer `0`, expected a nonzero usize"
            )),
            "{error:?}"
        );
    }

    #[test]
    fn core_logs_are_queued_as_log_events() -> Checked {
        let runtime = new_runtime(br#"{"threads":1,"log":"warn"}"#)?;

        runtime.core.tokio_handle().block_on(async {
            tracing::warn!(target: "temporalio_sdk_core", answer = 42, "core warning");
        });

        let entry = log_entry(&runtime)?;
        assert_eq!(entry["level"], "WARN");
        assert_eq!(entry["target"], "temporalio_sdk_core");
        assert_eq!(entry["message"], "core warning");
        assert_eq!(entry["fields"]["answer"], 42);
        Ok(())
    }

    fn messages(events: &[Event]) -> Result<Vec<Value>, serde_json::Error> {
        events
            .iter()
            .filter(|(_, kind, _, _)| *kind == KIND_LOG)
            .map(|(_, _, _, data)| {
                serde_json::from_slice::<Value>(data).map(|e| e["message"].clone())
            })
            .collect()
    }

    #[test]
    fn pending_logs_stop_at_the_cap_and_the_loss_is_logged_when_logs_fit_again() -> Checked {
        let runtime = new_runtime(br#"{"threads":1,"log":"off"}"#)?;
        let rt = std::ptr::from_ref(&runtime).cast_mut();
        let consumer = QueueLog(Arc::new(OnceLock::from(runtime.queue.clone())));
        let log = |message: String| CoreLog {
            target: "temporalio_sdk_core".into(),
            message,
            timestamp: std::time::SystemTime::now(),
            level: tracing::Level::DEBUG,
            fields: HashMap::new(),
            span_contexts: Vec::new(),
        };

        for i in 0..MAX_PENDING_LOGS + 3 {
            consumer.on_log(log(format!("flood {i}")));
        }
        runtime
            .queue
            .push(1, KIND_ACTIVITY_TASK, STATUS_OK, Vec::new());

        let flood = drain_events(rt);
        assert_eq!(flood.len(), MAX_PENDING_LOGS + 1);
        assert_eq!(
            flood.last(),
            Some(&(1, KIND_ACTIVITY_TASK, STATUS_OK, vec![]))
        );
        assert_eq!(
            messages(&flood)?.last(),
            Some(&Value::from(format!("flood {}", MAX_PENDING_LOGS - 1)))
        );

        consumer.on_log(log("after".into()));

        let after = drain_events(rt);
        assert_eq!(after.len(), 2);
        let notice: Value = serde_json::from_slice(&after[0].3)?;
        assert_eq!(notice["level"], "WARN");
        assert_eq!(notice["target"], "temporal_php_bridge");
        assert_eq!(
            notice["message"],
            "3 log records were dropped: the event queue was full"
        );
        assert_eq!(messages(&after[1..])?, vec![Value::from("after")]);
        Ok(())
    }

    #[test]
    fn worker_heartbeat_interval_outside_one_to_sixty_seconds_is_an_error() -> Checked {
        let config = br#"{"threads":1,"log":"off","worker_heartbeat_interval_ms":500}"#;

        assert_eq!(
            new_runtime(config).err().as_deref(),
            Some("heartbeat_interval (500ms) must be between 1s and 60s")
        );
        new_runtime(br#"{"threads":1,"log":"off","worker_heartbeat_interval_ms":1000}"#)?;
        Ok(())
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
