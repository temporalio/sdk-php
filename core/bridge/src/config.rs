use serde::{Deserialize, de::DeserializeOwned};
use std::time::Duration;
use temporalio_client::{ClientTlsOptions, ConnectionOptions, GrpcCompression, TlsOptions};
use temporalio_common::{
    protos::temporal::api::enums::v1::VersioningBehavior,
    worker::{WorkerDeploymentOptions, WorkerDeploymentVersion, WorkerTaskTypes},
};
use temporalio_sdk_core::{PollerBehavior, Url, WorkerConfig, WorkerVersioningStrategy};
use tonic::transport::{Certificate, ClientTlsConfig, Identity, Uri};

pub fn parse<T: DeserializeOwned>(json: &[u8], what: &str) -> Result<T, String> {
    serde_json::from_slice(json).map_err(|e| format!("Invalid {what} JSON: {e}"))
}

#[derive(Deserialize)]
#[serde(deny_unknown_fields)]
pub struct RuntimeJson {
    pub threads: usize,
    pub log: Option<String>,
}

#[derive(Deserialize)]
#[serde(deny_unknown_fields)]
pub struct TlsJson {
    server_root_ca_cert: Option<String>,
    domain: Option<String>,
    client_cert: Option<String>,
    client_private_key: Option<String>,
}

impl TlsJson {
    fn options(self) -> TlsOptions {
        TlsOptions::builder()
            .maybe_server_root_ca_cert(self.server_root_ca_cert.map(String::into_bytes))
            .maybe_domain(self.domain)
            .maybe_client_tls_options(self.client_cert.zip(self.client_private_key).map(
                |(client_cert, client_private_key)| {
                    ClientTlsOptions::builder()
                        .client_cert(client_cert.into_bytes())
                        .client_private_key(client_private_key.into_bytes())
                        .build()
                },
            ))
            .build()
    }

    pub fn client_config(self) -> Result<(ClientTlsConfig, Option<Uri>), String> {
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
        if let (Some(cert), Some(key)) = (self.client_cert, self.client_private_key) {
            config = config.identity(Identity::from_pem(cert, key));
        }
        Ok((config, origin))
    }
}

#[derive(Deserialize)]
#[serde(deny_unknown_fields, rename_all = "lowercase")]
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
                .maybe_tls_options(self.tls.map(TlsJson::options))
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
    pub connection: ConnectionJson,
    namespace: String,
    task_queue: String,
    workflows: bool,
    activities: bool,
    no_remote_activities: bool,
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
}

impl WorkerJson {
    pub fn worker_config(&self) -> Result<WorkerConfig, String> {
        WorkerConfig::builder()
            .namespace(self.namespace.as_str())
            .task_queue(self.task_queue.as_str())
            .versioning_strategy(self.versioning_strategy()?)
            .max_cached_workflows(self.max_cached_workflows)
            .max_outstanding_workflow_tasks(self.max_outstanding_workflow_tasks)
            .max_outstanding_activities(self.max_outstanding_activities)
            .max_outstanding_local_activities(self.max_outstanding_local_activities)
            .workflow_task_poller_behavior(PollerBehavior::SimpleMaximum(
                self.max_concurrent_workflow_task_polls,
            ))
            .activity_task_poller_behavior(PollerBehavior::SimpleMaximum(
                self.max_concurrent_activity_task_polls,
            ))
            .nonsticky_to_sticky_poll_ratio(self.nonsticky_to_sticky_poll_ratio)
            .sticky_queue_schedule_to_start_timeout(Duration::from_millis(
                self.sticky_queue_schedule_to_start_timeout_ms,
            ))
            .graceful_shutdown_period(Duration::from_millis(self.graceful_shutdown_period_ms))
            .maybe_max_worker_activities_per_second(self.max_worker_activities_per_second)
            .maybe_max_task_queue_activities_per_second(self.max_task_queue_activities_per_second)
            .task_types(WorkerTaskTypes {
                enable_workflows: self.workflows,
                enable_local_activities: self.workflows && self.activities,
                enable_remote_activities: self.activities && !self.no_remote_activities,
                enable_nexus: false,
            })
            .build()
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
        let default_versioning_behavior = match deployment.default_versioning_behavior {
            0 => None,
            v => Some(
                VersioningBehavior::try_from(v)
                    .map_err(|e| e.to_string())?
                    .into(),
            ),
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
    use serde_json::{Value, json};

    fn worker() -> Value {
        json!({
            "connection": {
                "target_url": "http://127.0.0.1:7233",
                "client_name": "temporal-php-2",
                "client_version": "2.0.0",
                "identity": "1@host",
                "api_key": null,
                "tls": null,
                "connect_timeout_ms": 10000,
                "grpc_compression": "gzip",
            },
            "namespace": "default",
            "task_queue": "q",
            "workflows": true,
            "activities": true,
            "no_remote_activities": false,
            "deployment": null,
            "build_id": "",
            "graceful_shutdown_period_ms": 1500,
            "max_worker_activities_per_second": 2.5,
            "max_task_queue_activities_per_second": null,
            "max_cached_workflows": 10000,
            "max_outstanding_workflow_tasks": 100,
            "max_outstanding_activities": 1,
            "max_outstanding_local_activities": 1,
            "max_concurrent_workflow_task_polls": 8,
            "max_concurrent_activity_task_polls": 1,
            "nonsticky_to_sticky_poll_ratio": 0.5,
            "sticky_queue_schedule_to_start_timeout_ms": 5000,
        })
    }

    fn parse_worker(json: &Value) -> Result<WorkerJson, String> {
        parse(json.to_string().as_bytes(), "worker config")
    }

    #[test]
    fn worker_config_maps_every_key() {
        let config = parse_worker(&worker()).unwrap().worker_config().unwrap();
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
        assert!(matches!(
            config.versioning_strategy,
            WorkerVersioningStrategy::None { .. }
        ));
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
        let config = parse_worker(&json).unwrap().worker_config().unwrap();
        let WorkerVersioningStrategy::WorkerDeploymentBased(options) = config.versioning_strategy
        else {
            panic!("deployment-based versioning expected");
        };
        assert!(options.use_worker_versioning);
        assert_eq!(options.version.deployment_name, "app");
        assert_eq!(options.version.build_id, "b1");
        assert!(options.default_versioning_behavior.is_some());
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
        let options = parse_worker(&json).unwrap().connection.options().unwrap();
        assert_eq!(options.connect_timeout, Some(Duration::from_secs(10)));
        assert_eq!(options.grpc_compression, GrpcCompression::None);
        assert_eq!(
            options.tls_options.unwrap().domain.as_deref(),
            Some("example.com")
        );
    }

    #[test]
    fn runtime_config_needs_threads() {
        let runtime: RuntimeJson = parse(br#"{"threads":2,"log":null}"#, "runtime").unwrap();
        assert_eq!(runtime.threads, 2);
        assert!(parse::<RuntimeJson>(br#"{"log":"info"}"#, "runtime").is_err());
        assert!(parse::<RuntimeJson>(br#"{"threads":"2","log":null}"#, "runtime").is_err());
    }
}
