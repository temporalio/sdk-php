#define KIND_WORKFLOW_ACTIVATION 1

#define KIND_ACTIVITY_TASK 2

#define KIND_WORKFLOW_COMPLETED 3

#define KIND_ACTIVITY_COMPLETED 4

#define KIND_SHUTDOWN_FINALIZED 5

#define KIND_RPC_RESULT 6

#define STATUS_OK 0

#define STATUS_ERROR 1

#define STATUS_SHUTDOWN 2

typedef struct TpbClient TpbClient;

typedef struct TpbRuntime TpbRuntime;

typedef struct TpbWorker TpbWorker;

typedef struct TpbEvent {
  uint64_t tag;
  int32_t kind;
  int32_t status;
  uint8_t *data;
  size_t len;
} TpbEvent;

struct TpbClient *tpb_client_new(struct TpbRuntime *rt,
                                 const char *config,
                                 size_t config_len,
                                 uint8_t **err,
                                 size_t *err_len);

void tpb_client_call(struct TpbClient *c,
                     uint64_t tag,
                     const char *path,
                     size_t path_len,
                     const char *body,
                     size_t body_len,
                     const char *metadata,
                     size_t metadata_len,
                     uint64_t timeout_ms);

void tpb_client_free(struct TpbClient *c);

void tpb_bytes_free(uint8_t *data, size_t len);

int tpb_event_fd(struct TpbRuntime *rt);

size_t tpb_next_events(struct TpbRuntime *rt, int32_t timeout_ms, struct TpbEvent *out, size_t max);

struct TpbWorker *tpb_replayer_new(struct TpbRuntime *rt,
                                   const char *config,
                                   size_t config_len,
                                   const char *history,
                                   size_t history_len,
                                   const char *workflow_id,
                                   size_t workflow_id_len,
                                   uint8_t **err,
                                   size_t *err_len);

struct TpbRuntime *tpb_runtime_new(const char *config,
                                   size_t config_len,
                                   uint8_t **err,
                                   size_t *err_len);

void tpb_runtime_free(struct TpbRuntime *rt);

struct TpbWorker *tpb_worker_new(struct TpbRuntime *rt,
                                 const char *config,
                                 size_t config_len,
                                 uint8_t **err,
                                 size_t *err_len);

void tpb_poll_workflow_activation(struct TpbWorker *w, uint64_t tag);

void tpb_poll_activity_task(struct TpbWorker *w, uint64_t tag);

void tpb_complete_workflow_activation(struct TpbWorker *w,
                                      uint64_t tag,
                                      const char *data,
                                      size_t len);

void tpb_complete_activity_task(struct TpbWorker *w, uint64_t tag, const char *data, size_t len);

int32_t tpb_record_activity_heartbeat(struct TpbWorker *w, const char *data, size_t len);

int32_t tpb_request_workflow_eviction(struct TpbWorker *w, const char *run_id, size_t len);

int32_t tpb_worker_initiate_shutdown(struct TpbWorker *w);

void tpb_worker_finalize_shutdown(struct TpbWorker *w, uint64_t tag);

void tpb_worker_free(struct TpbWorker *w);
