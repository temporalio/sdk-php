typedef struct TpbRuntime TpbRuntime;
typedef struct TpbWorker TpbWorker;
typedef struct TpbEvent { uint64_t tag; int32_t kind; int32_t status; uint8_t* data; size_t len; } TpbEvent;

TpbRuntime* tpb_runtime_new(void);
void tpb_runtime_free(TpbRuntime* rt);
int tpb_event_fd(TpbRuntime* rt);
size_t tpb_next_events(TpbRuntime* rt, int32_t timeout_ms, TpbEvent* out, size_t max);
void tpb_bytes_free(uint8_t* data, size_t len);

TpbWorker* tpb_worker_new(TpbRuntime* rt, const char* config, size_t config_len, uint8_t** err, size_t* err_len);
TpbWorker* tpb_replayer_new(TpbRuntime* rt, const char* config, size_t config_len, const char* history, size_t history_len, uint8_t** err, size_t* err_len);

void tpb_poll_workflow_activation(TpbWorker* w, uint64_t tag);
void tpb_poll_activity_task(TpbWorker* w, uint64_t tag);
void tpb_complete_workflow_activation(TpbWorker* w, uint64_t tag, const char* data, size_t len);
void tpb_complete_activity_task(TpbWorker* w, uint64_t tag, const char* data, size_t len);
int32_t tpb_record_activity_heartbeat(TpbWorker* w, const char* data, size_t len);
void tpb_request_workflow_eviction(TpbWorker* w, const char* run_id, size_t len);
void tpb_worker_initiate_shutdown(TpbWorker* w);
void tpb_worker_finalize_shutdown(TpbWorker* w, uint64_t tag);
void tpb_worker_free(TpbWorker* w);
