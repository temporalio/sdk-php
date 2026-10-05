use serde::{Deserialize, de::DeserializeOwned};
use serde_json::{Map, Value};
use std::collections::HashSet;
use std::num::NonZeroUsize;
use std::time::Duration;
use temporalio_client::{ClientTlsOptions, ConnectionOptions, GrpcCompression, TlsOptions};
use temporalio_common::{
    protos::temporal::api::enums::v1::VersioningBehavior,
    worker::{WorkerDeploymentOptions, WorkerDeploymentVersion, WorkerTaskTypes},
};
use temporalio_sdk_core::{
    PollerBehavior, Url, WorkerConfig, WorkerVersioningStrategy, WorkflowErrorType,
};
use tonic::transport::{Certificate, ClientTlsConfig, Identity, Uri};

const AUTOSCALING_MIN_POLLERS: usize = 1;
const AUTOSCALING_INITIAL_POLLERS: usize = 5;

pub fn parse<T: DeserializeOwned>(json: &[u8], what: &str) -> Result<T, String> {
    serde_json::from_slice(json).map_err(|e| format!("Invalid {what} JSON: {e}"))
}

#[derive(Deserialize)]
#[serde(deny_unknown_fields)]
pub struct RuntimeJson {
    pub threads: NonZeroUsize,
    pub log: String,
    pub prometheus: Option<String>,
}

#[derive(Deserialize)]
#[serde(deny_unknown_fields)]
pub struct TlsJson {
    server_root_ca_cert: Option<String>,
    domain: Option<String>,
    client_cert: Option<String>,
    client_private_key: Option<String>,
}

fn client_identity(
    client_cert: Option<String>,
    client_private_key: Option<String>,
) -> Result<Option<(String, String)>, String> {
    match (client_cert, client_private_key) {
        (Some(cert), Some(key)) => Ok(Some((cert, key))),
        (None, None) => Ok(None),
        _ => Err("client_cert and client_private_key must be set together".into()),
    }
}

impl TlsJson {
    fn options(self) -> Result<TlsOptions, String> {
        let identity = client_identity(self.client_cert, self.client_private_key)?;
        Ok(TlsOptions::builder()
            .maybe_server_root_ca_cert(self.server_root_ca_cert.map(String::into_bytes))
            .maybe_domain(self.domain)
            .maybe_client_tls_options(identity.map(|(client_cert, client_private_key)| {
                ClientTlsOptions::builder()
                    .client_cert(client_cert.into_bytes())
                    .client_private_key(client_private_key.into_bytes())
                    .build()
            }))
            .build())
    }

    pub fn client_config(self) -> Result<(ClientTlsConfig, Option<Uri>), String> {
        let identity = client_identity(self.client_cert, self.client_private_key)?;
        let mut config = match self.server_root_ca_cert {
            Some(ca) => ClientTlsConfig::new().ca_certificate(Certificate::from_pem(ca)),
            None => ClientTlsConfig::new().with_native_roots(),
        };
        let mut origin = None;
        if let Some(domain) = self.domain {
            origin = Some(
                format!("https://{domain}")
                    .parse()
                    .map_err(|e| format!("Invalid TLS domain: {e}"))?,
            );
            config = config.domain_name(domain);
        }
        if let Some((cert, key)) = identity {
            config = config.identity(Identity::from_pem(cert, key));
        }
        Ok((config, origin))
    }
}

#[derive(Deserialize)]
#[serde(rename_all = "lowercase")]
enum Compression {
    Gzip,
    None,
}

#[derive(Deserialize)]
#[serde(deny_unknown_fields)]
pub struct ConnectionJson {
    target_url: String,
    client_name: String,
    client_version: String,
    identity: String,
    api_key: Option<String>,
    tls: Option<TlsJson>,
    connect_timeout_ms: u64,
    grpc_compression: Compression,
}

impl ConnectionJson {
    pub fn options(self) -> Result<ConnectionOptions, String> {
        Ok(
            ConnectionOptions::new(Url::parse(&self.target_url).map_err(|e| e.to_string())?)
                .client_name(self.client_name)
                .client_version(self.client_version)
                .identity(self.identity)
                .maybe_api_key(self.api_key)
                .maybe_tls_options(self.tls.map(TlsJson::options).transpose()?)
                .connect_timeout(Duration::from_millis(self.connect_timeout_ms))
                .grpc_compression(match self.grpc_compression {
                    Compression::Gzip => GrpcCompression::Gzip,
                    Compression::None => GrpcCompression::None,
                })
                .build(),
        )
    }
}

#[derive(Deserialize)]
#[serde(deny_unknown_fields)]
pub struct ClientJson {
    pub target_url: String,
    pub tls: Option<TlsJson>,
    pub connect_timeout_ms: u64,
}

#[derive(Deserialize)]
#[serde(deny_unknown_fields, rename_all = "PascalCase")]
struct DeploymentVersionJson {
    deployment_name: String,
    build_id: String,
}

#[derive(Deserialize)]
#[serde(deny_unknown_fields, rename_all = "PascalCase")]
struct DeploymentJson {
    use_versioning: bool,
    version: Option<DeploymentVersionJson>,
    default_versioning_behavior: i32,
}

#[derive(Deserialize)]
#[serde(deny_unknown_fields)]
pub struct WorkerJson {
    namespace: String,
    task_queue: String,
    workflows: bool,
    local_activities: bool,
    remote_activities: bool,
    deployment: Option<DeploymentJson>,
    build_id: String,
    graceful_shutdown_period_ms: u64,
    max_worker_activities_per_second: Option<f64>,
    max_task_queue_activities_per_second: Option<f64>,
    max_cached_workflows: usize,
    max_outstanding_workflow_tasks: usize,
    max_outstanding_activities: usize,
    max_outstanding_local_activities: usize,
    max_concurrent_workflow_task_polls: usize,
    max_concurrent_activity_task_polls: usize,
    nonsticky_to_sticky_poll_ratio: f32,
    sticky_queue_schedule_to_start_timeout_ms: u64,
    nondeterminism_fails_workflow: bool,
    max_heartbeat_throttle_interval_ms: Option<u64>,
    poller_autoscaling: bool,
}

impl WorkerJson {
    pub fn parse_with_connection(json: &[u8]) -> Result<(String, ConnectionJson, Self), String> {
        let mut fields: Map<String, Value> = parse(json, "worker config")?;
        let connection = fields.remove("connection").unwrap_or_default();
        Ok((
            connection.to_string(),
            ConnectionJson::deserialize(connection)
                .map_err(|e| format!("Invalid connection config JSON: {e}"))?,
            Self::deserialize(Value::Object(fields))
                .map_err(|e| format!("Invalid worker config JSON: {e}"))?,
        ))
    }

    pub fn worker_config(&self) -> Result<WorkerConfig, String> {
        WorkerConfig::builder()
            .namespace(self.namespace.as_str())
            .task_queue(self.task_queue.as_str())
            .versioning_strategy(self.versioning_strategy()?)
            .max_cached_workflows(self.max_cached_workflows)
            .max_outstanding_workflow_tasks(self.max_outstanding_workflow_tasks)
            .max_outstanding_activities(self.max_outstanding_activities)
            .max_outstanding_local_activities(self.max_outstanding_local_activities)
            .workflow_task_poller_behavior(
                self.poller_behavior(self.max_concurrent_workflow_task_polls),
            )
            .activity_task_poller_behavior(
                self.poller_behavior(self.max_concurrent_activity_task_polls),
            )
            .maybe_max_heartbeat_throttle_interval(
                self.max_heartbeat_throttle_interval_ms
                    .map(Duration::from_millis),
            )
            .nonsticky_to_sticky_poll_ratio(self.nonsticky_to_sticky_poll_ratio)
            .sticky_queue_schedule_to_start_timeout(Duration::from_millis(
                self.sticky_queue_schedule_to_start_timeout_ms,
            ))
            .graceful_shutdown_period(Duration::from_millis(self.graceful_shutdown_period_ms))
            .maybe_max_worker_activities_per_second(self.max_worker_activities_per_second)
            .maybe_max_task_queue_activities_per_second(self.max_task_queue_activities_per_second)
            .workflow_failure_errors(if self.nondeterminism_fails_workflow {
                HashSet::from([WorkflowErrorType::Nondeterminism])
            } else {
                HashSet::new()
            })
            .task_types(WorkerTaskTypes {
                enable_workflows: self.workflows,
                enable_local_activities: self.local_activities,
                enable_remote_activities: self.remote_activities,
                enable_nexus: false,
            })
            .build()
    }

    fn poller_behavior(&self, maximum: usize) -> PollerBehavior {
        if self.poller_autoscaling {
            PollerBehavior::Autoscaling {
                minimum: AUTOSCALING_MIN_POLLERS,
                maximum,
                initial: maximum.min(AUTOSCALING_INITIAL_POLLERS),
            }
        } else {
            PollerBehavior::SimpleMaximum(maximum)
        }
    }

    fn versioning_strategy(&self) -> Result<WorkerVersioningStrategy, String> {
        let Some((deployment, version)) = self
            .deployment
            .as_ref()
            .and_then(|d| d.version.as_ref().map(|v| (d, v)))
        else {
            return Ok(WorkerVersioningStrategy::None {
                build_id: self.build_id.clone(),
            });
        };
        let default_versioning_behavior =
            match VersioningBehavior::try_from(deployment.default_versioning_behavior)
                .map_err(|e| e.to_string())?
            {
                VersioningBehavior::Unspecified => None,
                behavior => Some(behavior.into()),
            };
        Ok(WorkerVersioningStrategy::WorkerDeploymentBased(
            WorkerDeploymentOptions::new(
                WorkerDeploymentVersion::builder()
                    .deployment_name(version.deployment_name.clone())
                    .build_id(version.build_id.clone())
                    .build(),
            )
            .use_worker_versioning(deployment.use_versioning)
            .maybe_default_versioning_behavior(default_versioning_behavior)
            .build(),
        ))
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::testing::worker_json;
    use serde_json::{Value, json};

    fn worker() -> Value {
        worker_json("http://127.0.0.1:7233")
    }

    fn parse_worker(json: &Value) -> Result<(ConnectionJson, WorkerJson), String> {
        WorkerJson::parse_with_connection(json.to_string().as_bytes())
            .map(|(_, connection, worker)| (connection, worker))
    }

    fn worker_config(json: &Value) -> Result<WorkerConfig, String> {
        parse_worker(json)?.1.worker_config()
    }

    #[test]
    fn workers_with_the_same_connection_share_the_connection_key() {
        let first = worker();
        let mut second = worker();
        second["task_queue"] = json!("another-queue");
        let mut other = worker();
        other["connection"]["identity"] = json!("2@host");

        let key = |json: &Value| {
            WorkerJson::parse_with_connection(json.to_string().as_bytes())
                .unwrap()
                .0
        };

        assert_eq!(key(&first), key(&second));
        assert_ne!(key(&first), key(&other));
    }

    fn tls(client_cert: Option<&str>, client_private_key: Option<&str>) -> TlsJson {
        TlsJson {
            server_root_ca_cert: None,
            domain: None,
            client_cert: client_cert.map(str::to_owned),
            client_private_key: client_private_key.map(str::to_owned),
        }
    }

    #[test]
    fn worker_config_maps_every_key() {
        let config = worker_config(&worker()).unwrap();
        assert_eq!(config.namespace, "default");
        assert_eq!(config.task_queue, "q");
        assert_eq!(config.max_cached_workflows, 10000);
        assert_eq!(config.max_outstanding_workflow_tasks, Some(100));
        assert_eq!(config.nonsticky_to_sticky_poll_ratio, 0.5);
        assert_eq!(
            config.sticky_queue_schedule_to_start_timeout,
            Duration::from_millis(5000)
        );
        assert_eq!(
            config.graceful_shutdown_period,
            Some(Duration::from_millis(1500))
        );
        assert_eq!(config.max_worker_activities_per_second, Some(2.5));
        assert_eq!(config.max_task_queue_activities_per_second, None);
        assert!(config.workflow_failure_errors.is_empty());
        assert!(matches!(
            config.workflow_task_poller_behavior,
            Some(PollerBehavior::SimpleMaximum(8))
        ));
        assert!(matches!(
            config.versioning_strategy,
            WorkerVersioningStrategy::None { .. }
        ));
        assert!(config.task_types.enable_workflows);
        assert!(config.task_types.enable_local_activities);
        assert!(!config.task_types.enable_remote_activities);
    }

    #[test]
    fn worker_config_maps_autoscaling_heartbeat_throttle_and_nondeterminism() {
        let mut json = worker();
        json["poller_autoscaling"] = json!(true);
        json["max_heartbeat_throttle_interval_ms"] = json!(60000);
        json["nondeterminism_fails_workflow"] = json!(true);

        let config = worker_config(&json).unwrap();

        assert!(matches!(
            config.workflow_task_poller_behavior,
            Some(PollerBehavior::Autoscaling {
                minimum: 1,
                maximum: 8,
                initial: 5
            })
        ));
        assert!(matches!(
            config.activity_task_poller_behavior,
            Some(PollerBehavior::Autoscaling {
                minimum: 1,
                maximum: 1,
                initial: 1
            })
        ));
        assert_eq!(
            config.max_heartbeat_throttle_interval,
            Duration::from_secs(60)
        );
        assert_eq!(
            config.workflow_failure_errors,
            HashSet::from([WorkflowErrorType::Nondeterminism])
        );
    }

    #[test]
    fn worker_config_rejects_missing_unknown_and_mistyped_keys() {
        let mut missing = worker();
        missing.as_object_mut().unwrap().remove("task_queue");
        assert!(parse_worker(&missing).err().unwrap().contains("task_queue"));

        let mut missing = worker();
        missing["connection"]
            .as_object_mut()
            .unwrap()
            .remove("client_name");
        assert!(
            parse_worker(&missing)
                .err()
                .unwrap()
                .contains("client_name")
        );

        let mut unknown = worker();
        unknown["max_cached_workflow"] = json!(1);
        assert!(
            parse_worker(&unknown)
                .err()
                .unwrap()
                .contains("max_cached_workflow")
        );

        let mut mistyped = worker();
        mistyped["max_cached_workflows"] = json!("10");
        assert!(parse_worker(&mistyped).is_err());

        let mut compression = worker();
        compression["connection"]["grpc_compression"] = json!("zstd");
        assert!(parse_worker(&compression).err().unwrap().contains("zstd"));
    }

    #[test]
    fn worker_config_reads_deployment() {
        let mut json = worker();
        json["deployment"] = json!({
            "UseVersioning": true,
            "Version": {"DeploymentName": "app", "BuildId": "b1"},
            "DefaultVersioningBehavior": 2,
        });
        let auto_upgrade = Some(VersioningBehavior::AutoUpgrade.into());
        assert!(matches!(
            worker_config(&json).unwrap().versioning_strategy,
            WorkerVersioningStrategy::WorkerDeploymentBased(options)
                if options.use_worker_versioning
                    && options.version.deployment_name == "app"
                    && options.version.build_id == "b1"
                    && options.default_versioning_behavior == auto_upgrade
        ));

        json["deployment"]["DefaultVersioningBehavior"] = json!(0);
        assert!(matches!(
            worker_config(&json).unwrap().versioning_strategy,
            WorkerVersioningStrategy::WorkerDeploymentBased(options)
                if options.default_versioning_behavior.is_none()
        ));

        json["deployment"]["DefaultVersioningBehavior"] = json!(99);
        assert!(worker_config(&json).is_err());
    }

    #[test]
    fn replayer_config_is_the_worker_part_without_connection() {
        let mut json = worker();
        json.as_object_mut().unwrap().remove("connection");
        let config: WorkerJson = parse(json.to_string().as_bytes(), "worker config").unwrap();
        assert_eq!(config.worker_config().unwrap().task_queue, "q");
        assert!(parse::<WorkerJson>(worker().to_string().as_bytes(), "worker config").is_err());
        assert!(parse_worker(&json).err().unwrap().contains("connection"));
    }

    #[test]
    fn client_cert_and_key_must_be_set_together() {
        let error = "client_cert and client_private_key must be set together";
        for (cert, key) in [(Some("cert"), None), (None, Some("key"))] {
            assert_eq!(tls(cert, key).options().err().unwrap(), error);
            assert_eq!(tls(cert, key).client_config().err().unwrap(), error);
        }
        assert!(
            tls(None, None)
                .options()
                .unwrap()
                .client_tls_options
                .is_none()
        );
        assert!(
            tls(Some("cert"), Some("key"))
                .options()
                .unwrap()
                .client_tls_options
                .is_some()
        );
        assert!(tls(None, None).client_config().is_ok());
        assert!(tls(Some("cert"), Some("key")).client_config().is_ok());
    }

    #[test]
    fn client_tls_config_reads_the_ca_and_the_domain() {
        let with_domain = |domain: &str| TlsJson {
            server_root_ca_cert: Some("ca".into()),
            domain: Some(domain.into()),
            ..tls(Some("cert"), Some("key"))
        };

        let (_, origin) = with_domain("example.com").client_config().unwrap();

        assert_eq!(origin.unwrap(), "https://example.com/");
        assert!(
            with_domain("bad domain")
                .client_config()
                .err()
                .unwrap()
                .starts_with("Invalid TLS domain: ")
        );
    }

    #[test]
    fn connection_options_read_tls_timeout_and_compression() {
        let mut json = worker();
        json["connection"]["tls"] = json!({
            "server_root_ca_cert": null,
            "domain": "example.com",
            "client_cert": null,
            "client_private_key": null,
        });
        json["connection"]["grpc_compression"] = json!("none");
        let options = parse_worker(&json).unwrap().0.options().unwrap();
        assert_eq!(options.connect_timeout, Some(Duration::from_secs(10)));
        assert_eq!(options.grpc_compression, GrpcCompression::None);
        assert_eq!(
            options.tls_options.unwrap().domain.as_deref(),
            Some("example.com")
        );
    }

    #[test]
    fn runtime_config_needs_threads() {
        let runtime: RuntimeJson = parse(br#"{"threads":2,"log":"off"}"#, "runtime").unwrap();
        assert_eq!(runtime.threads.get(), 2);
        assert!(parse::<RuntimeJson>(br#"{"log":"info"}"#, "runtime").is_err());
        assert!(parse::<RuntimeJson>(br#"{"threads":"2","log":"off"}"#, "runtime").is_err());
    }
}
