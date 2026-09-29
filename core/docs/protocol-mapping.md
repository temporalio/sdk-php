# RoadRunner ↔ PHP SDK ↔ sdk-core protocol mapping

Path aliases used below:

| Alias | Path |
|---|---|
| `PHP` | `sdk-php/.claude/worktrees/temporal-php-no-roadrunner-f8db35/src` |
| `RRT` | `roadrunner-temporal` (commit `81aba14`) |
| `GO` | `~/go/pkg/mod/go.temporal.io/sdk@v1.48.1-0.20260828153826-57cc5a7d6194/internal` (the version pinned in `RRT/go.mod:18`) |
| `CP` | `sdk-typescript/packages/core-bridge/sdk-core/crates/common/protos/local/temporal/sdk/core` |
| `CORE` | `sdk-typescript/packages/core-bridge/sdk-core/crates/sdk-core/src` |
| `PY` | `sdk-python/temporalio/worker/_workflow_instance.py` |
| `TS` | `sdk-typescript/packages/workflow/src` |

---

## 0. The two PHP seams for the adapter

| Seam | Where | What the adapter must do |
|---|---|---|
| Host connection | `PHP/WorkerFactory.php:257-271` (`run(?HostConnectionInterface $host)`, loop `waitBatch()` → `dispatch()` → `send()`) | Implement `HostConnectionInterface` (`waitBatch(): ?CommandBatch`, `send(string)`, `error()`). `CommandBatch{messages: string, context: array}`. The frame is decoded by `ProtoCodec` only when `$_SERVER['RR_CODEC']` is `proto`/`protobuf` (`WorkerFactory.php:353-361`). Otherwise `JsonCodec` is used. |
| Direct objects | `WorkerFactory::dispatch()` `PHP/WorkerFactory.php:367-386` | Build `ServerRequest`/`SuccessResponse`/`FailureResponse` with a `TickInfo`, call `env->update()`, `client->dispatch()` / `server->dispatch($cmd, $headers)`, then `tick()`, then read the `ArrayQueue`. This is private code, so the seam is only usable through a subclass or a change. |
| RPC | `PHP/Worker/Transport/RPCConnectionInterface.php` (`call(string $method, $payload)`), injected by `WorkerFactory::create(rpc:)` `WorkerFactory.php:151-165` | Implement `call('temporal.RecordActivityHeartbeat', …)`. |

`WorkerFactory::dispatch()` semantics (`PHP/WorkerFactory.php:367-386`):
1. Decode every message of the batch. For each message: `env->update(TickInfo)` (sets `isReplaying`, `tickTime`: `PHP/Worker/Environment/Environment.php:36-40`).
2. `ServerResponseInterface` → `Client::dispatch()` resolves or rejects the `Deferred` of the request with that `id` (`PHP/Internal/Transport/Client.php:48-70`). An unknown id pushes `UndefinedResponse` (`Client.php:52`).
3. `ServerRequestInterface` → `Server::dispatch()` → route. `headers['taskQueue']` absent → factory router (only `GetWorkerInfo`). Present → `Worker::dispatch()` (`WorkerFactory.php:388-398`).
4. `tick()` once: `ON_SIGNAL`, `ON_CALLBACK`, `ON_QUERY`, `ON_TICK`, `ON_FINALLY` (`WorkerFactory.php:278-285`).
5. Encode the whole response queue (`ArrayQueue`) in push order and return it.

---

## 1. Batch / frame semantics (RR → PHP)

### 1.1 Frame

* Goridge payload: `body` = protobuf `temporal.roadrunner.internal.Frame{repeated Message messages}`, `context` = JSON `internal.Context` (`RRT/internal/codec/proto/proto.go:37-79`).
* `Message` fields (`vendor/roadrunner-php/roadrunner-api-dto/.../Temporal/Roadrunner/Internal/Message.php`): `id=1 uint64`, `command=2 string`, `options=3 bytes(JSON)`, `failure=4 Failure`, `payloads=5 Payloads`, `header=6 Header`, `history_length=7`, `run_id=8 string`, `task_queue=9`, `tick_time=10`, `replay=11`, `continue_as_new_suggested=12`, `history_size=13`, `wwpid=14`.
* Every message of one frame gets the same context fields copied in (`proto.go:148-167`). `run_id` is the **RR workflow instance UUID** (`rr_id`), not the Temporal run ID (`proto.go:156`, `RRT/aggregatedpool/workflow.go:81,99`).

### 1.2 Context JSON (goridge `context`) — `RRT/internal/protocol.go:74-92`

| JSON key | Go source (`RRT/aggregatedpool/handler.go:27-37`) | PHP use |
|---|---|---|
| `taskQueue` (omitempty) | `WorkflowInfo().TaskQueueName` | Routing to the `Worker` (`WorkerFactory.php:388-398`). Activities: `info.TaskQueue` (`activity.go:100-103`). Empty for `GetWorkerInfo`. |
| `tickTime` (omitempty) | `env.Now().Format(time.RFC3339)` — **second precision** | `TickInfo.time` (`ProtoCodec.php:65-71`, header wins over message field) |
| `replay` (omitempty) | `env.IsReplaying()` | `TickInfo.isReplaying` |
| `history_length` (omitempty) | `GetCurrentHistoryLength()` | `TickInfo.historyLength` |
| `history_size` (omitempty) | `GetCurrentHistorySize()` | `TickInfo.historySize` |
| `continue_as_new_suggested` | `GetContinueAsNewSuggested()` | `TickInfo.continueAsNewSuggested` |
| `rr_id` | per-instance UUID | not read from the context; read from `Message.run_id` |

`TickInfo::applyTo()` copies `historyLength/historySize/continueAsNewSuggested` into `WorkflowInfo` (`PHP/Worker/Transport/Command/Server/TickInfo.php`). `Client::dispatch()` applies it only when the new `historyLength` is larger (`Client.php:59-61`).

### 1.3 IDs and correlation

| Direction | `id` | `run_id` | Meaning |
|---|---|---|---|
| RR→PHP server request (`command != ""`) | global RR `seq()` (`workflow.go:41-46`, `queue/queue.go:41-51`) — PHP ignores it | `rr_id` | PHP `ServerRequest::getID()` = `run_id ?: options.info.WorkflowExecution.RunID ?: options.runId` (`Decoder.php:58`, `ServerRequest.php:51`). It is the **process key** in `ProcessCollection` (`StartWorkflow.php:84-86`). |
| RR→PHP response (`command == ""`) | the PHP request `id` | `rr_id` | `Failure` set → `FailureResponse`, else `SuccessResponse` (`Decoder.php:34-41`). |
| PHP→RR command (`Request`) | PHP request id: global counter, seeded from `microtime*1e6` (`Request.php:30,54-84`) | – | int64. **It does not fit core `uint32 seq`.** |
| PHP→RR response to a server request | `SuccessClientResponse/FailedClientResponse(id = ServerRequest id)` (`Server.php:89-115`). The id is a string, so `Encoder` does not set `Message.id` (`Encoder.php:65,71`) → `id=0` | – | RR ignores non-command messages in the workflow pipeline (`workflow.go:317-334`). `runCommand` and activities expect **exactly one** message (`handler.go:733-739`, `activity.go:141`). |
| PHP→RR `UpdateResponse` | `0` | – | command `UpdateValidated`/`UpdateCompleted`, options `{"id": updateId}` (`PHP/Worker/Transport/Command/Client/UpdateResponse.php`, `Encoder.php:77-90`). |

### 1.4 One batch = one workflow run, one exchange

* Each RR `Workflow` instance = one run (`NewWorkflowDefinition()`, `workflow.go:97-116`). All messages of a workflow frame belong to that run. PHP has one dedicated workflow process (`RRT/internal.go:73-98`). Activities go to the activity pool, one activity per frame (`activity.go:82-103`).
* **When RR sends the frame (the "tick")**: sdk-go applies the history events of one WFT. During the event handling:
  * `handleSignal` and `handleCancel` push `InvokeSignal`/`CancelWorkflow` into the queue **immediately** (`handler.go:92-115`).
  * `Execute()` pushes `StartWorkflow` (`workflow.go:268-273`).
  * Result callbacks (activity/timer/child/…) are **deferred** into `wp.callbacks` when not in the loop (`handler.go:570-600`).
  * Updates are queued in sdk-go (`env.QueueUpdate`, `handler.go:88`).
* Then `OnWorkflowTaskStarted()` (`workflow.go:282-335`):
  1. run the deferred callbacks → `PushResponse`/`PushError` in event order;
  2. `HandleQueuedUpdates` → push `InvokeUpdate` (`workflow.go:301-309`, `handler.go:47-86`);
  3. `flushQueue()` → **one** frame to PHP. PHP returns all commands in one frame (`workflow.go:626-678`);
  4. process the returned commands **in order**. `GetVersion`, `SideEffect`, and `Cancel` push a response and call `flushQueue()` again **inside the same WFT** (synchronous round trip). The new PHP commands go to the end of the pipeline (`handler.go:215-242,501-514,603-623`).
  5. During the loop (`inLoop=1`), a callback that fires synchronously (timer 0, cancelled timer, TRY_CANCEL activity) pushes its response immediately, so it goes out with the next flush (`handler.go:586-592`).
* The resulting order inside one RR frame for a WFT is:
  `[StartWorkflow] → [InvokeSignal/CancelWorkflow in history order] → [responses to earlier commands, in history event order] → [InvokeUpdate…]`. Leftover queued messages from outside the loop can come first.
* A frame is sent **only if the queue is not empty** (`workflow.go:629-631`). PHP is not called for a WFT without work.
* Queries, `StackTrace`, and `DestroyWorkflow` use `runCommand()`: a separate frame with a single message, and a single-message reply (`handler.go:681-740`).
* `UndefinedResponse` from PHP → RR panics the WFT (`workflow.go:321-325`).

---

## 2. RR → PHP server requests

Command names: `RRT/internal/protocol.go:16-52`. The `options` JSON is Go `json.Marshal` of the struct (goccy/go-json, `proto.go:175`). Go structs without tags use the **Go field names**. `time.Duration` = int nanoseconds. `time.Time` = RFC3339Nano. `[]byte` = base64. Proto structs use their `json:"snake_case"` tags.

| Command | `options` JSON | payloads / header | PHP handler | PHP reply | RR use of the reply |
|---|---|---|---|---|---|
| `GetWorkerInfo` | `{"rr_version":"…","ww_pid":N}` (`protocol.go:136-139`, `info.go:18-21`). Context has no `taskQueue`. | – | `PHP/Internal/Transport/Router/GetWorkerInfo.php` | one message `id=0`, payloads = N JSON values, one per worker: `{"TaskQueue","Options"(marshalled WorkerOptions),"Workflows":[{"name","queries","signals","versioning_behavior"}],"Activities":[{"Name"}],"PhpSdkVersion","Plugins":[{"Name","Version"}],"Flags":{"ApiKey"}}` | `DecodeWorkerInfo` (`proto.go:114-146`) → `worker.New` + registration (`aggregatedpool/workers.go:121-220`), SDK name/version header (`internal.go:154-219`) |
| `StartWorkflow` | `{"info": workflow.Info, "lastCompletion": N?, "search_attributes": {name:{"type","value"}}?}` (`protocol.go:163-170`, `workflow.go:137-266`) | payloads = input + N last-completion payloads appended at the end. header = start header. | `StartWorkflow.php:44-89` | success `null` | ignored |
| `InvokeSignal` | `{"runId","name"}` (`protocol.go:173-179`) | signal args, signal header | `InvokeSignal.php` (handler queued on `ON_SIGNAL`) | success `null` | ignored |
| `InvokeQuery` | `{"runId","name"}` | query args, header | `InvokeQuery.php` (runs on `ON_QUERY`; built-ins `__temporal_workflow_metadata`, `__stack_trace`, `__enhanced_stack_trace`) | success `[result]` or failure | `handleQuery` returns payloads / `FailureToError` (`handler.go:118-137`) |
| `InvokeUpdate` | `{"updateId","runId","name","type":"validate_execute"}` (`protocol.go:189-198`, `handler.go:75-85`). **No `replay` key.** PHP reads `options.replay` (`InvokeUpdate.php:54`), so the validator always runs under RR. | update args, header | `InvokeUpdate.php` | route response is cancelled. PHP sends `UpdateValidated{id}` (failure = rejected) at once, then `UpdateCompleted{id}` with payloads `[result]` or failure. | `UpdateValidated`: when replaying, always `Accept()`. Else failure → `Reject()`, no failure → `Accept()` (`handler.go:49-61,261-280`). `UpdateCompleted` → `Complete(payloads|err)` (`handler.go:64-72,244-259`). |
| `CancelWorkflow` | `{"runId"}` | header | `CancelWorkflow.php` → `process->cancel()` | success `null` | ignored. The workflow then ends via `CompleteWorkflow` with a `CanceledFailure`. |
| `DestroyWorkflow` | `{"runId"}` | – | `DestroyWorkflow.php` (pulls the process at once, destroys on `ON_FINALLY`) | success `null` | ignored. Sent from `Close()` (`workflow.go:364-384`) at eviction or run end. |
| `StackTrace` | `{"runId"}` | – | `StackTrace.php` | success `[string]` | sdk-go deadlock/`__stack_trace` (`workflow.go:338-362`) |
| `InvokeActivity` | `{"name","info": activity.Info,"heartbeatDetails":N?}` (`protocol.go:142-151`, `activity.go:82-95`) | args + N heartbeat-detail payloads appended. header from the context (`api/context.go`). Context = `{"taskQueue":info.TaskQueue}` only. | `InvokeActivity.php:48-112` | success `[result]`. Failure. Failure with message `doNotCompleteOnReturn` = async completion (`DoNotCompleteOnResultException.php:21`). | `ErrResultPending` / `FailureToError` / payloads (`activity.go:145-154`) |
| `InvokeLocalActivity` | `{"name","info": activity.Info}`. `info.TaskToken` = bytes of a new UUID string (`local_activity.go:39-56`). | args, header | `InvokeLocalActivity` = `InvokeActivity` | same | same (`local_activity.go:105-114`) |

### 2.1 `StartWorkflow.options.info` = Go `workflow.Info` (`GO/workflow.go:1515-1583`)

Exported keys only: `WorkflowExecution{ID,RunID}`, `OriginalRunID`, `FirstRunID`, `WorkflowType{Name}`, `TaskQueueName`, `WorkflowExecutionTimeout`/`WorkflowRunTimeout`/`WorkflowTaskTimeout` (ns), `Namespace`, `Attempt`, `WorkflowStartTime` (RFC3339Nano), `CronSchedule`, `ContinuedExecutionRunID`, `ParentWorkflowNamespace`, `ParentWorkflowExecution` (`null|{ID,RunID}`), `RootWorkflowExecution` (`null|{ID,RunID}`), `Memo` (proto `{"fields":{k:{"metadata":{k:b64},"data":b64}}}`), `SearchAttributes` (proto `{"indexed_fields":{…}}`), `RetryPolicy` (Go struct `{InitialInterval ns, BackoffCoefficient, MaximumInterval ns, MaximumAttempts, NonRetryableErrorTypes}`), `Priority{PriorityKey,FairnessKey,FairnessWeight}`, `BinaryChecksum`.

PHP reads (`PHP/Workflow/WorkflowInfo.php:32-155`): all of the above except `WorkflowStartTime`. It also reads `HistoryLength`, `HistorySize`, `ShouldContinueAsNew` (filled by `TickInfo`), and `TypedSearchAttributes` (built from `options.search_attributes`). `Memo` and `SearchAttributes` are parsed with `Memo/SearchAttributes::mergeFromJsonString` (`StartWorkflow.php:103-146`). `WorkflowExecution` also accepts `workflow_id`/`run_id` (`PHP/Workflow/WorkflowExecution.php:29-39`). `RetryOptions`/`Priority` accept both Go-style and snake keys (`PHP/Common/RetryOptions.php:70-113`, `PHP/Common/Priority.php:44-76`).

Typed search attributes (`options.search_attributes`): `{name: {"type": "bool"|"float64"|"int64"|"keyword"|"keyword_list"|"string"|"datetime", "value": …}}`. `datetime` is RFC3339 (`protocol.go:54-64`, `workflow.go:156-256`). Go derives the type from `env.TypedSearchAttributes()`.

### 2.2 `InvokeActivity.options.info` = Go `activity.Info` (`GO/activity.go:28-72`)

`TaskToken` (b64), `WorkflowType{Name}|null`, `WorkflowNamespace`, `WorkflowExecution{ID,RunID}`, `ActivityID`, `ActivityRunID`, `ActivityType{Name}`, `TaskQueue`, `Namespace`, `HeartbeatTimeout`/`ScheduleToCloseTimeout`/`StartToCloseTimeout` (ns), `ScheduledTime`/`StartedTime`/`Deadline` (RFC3339Nano), `Attempt`, `IsLocalActivity`, `Priority{…}`, `RetryPolicy{…}|null`.

PHP reads (`PHP/Activity/ActivityInfo.php:43-111`): `TaskToken` (b64-decoded first, `InvokeActivity.php:57-58`), `WorkflowType`, `WorkflowNamespace`, `WorkflowExecution`, `ActivityID`, `ActivityType`, `TaskQueue`, `HeartbeatTimeout`, `ScheduledTime`, `StartedTime`, `Deadline`, `Attempt`, `Priority`, `RetryPolicy`.

---

## 3. PHP → RR commands

Construction sites: `PHP/Internal/Transport/Request/*.php`. Options arrays come from the PHP `Marshaller`. Real output (measured with `php -r`):

```
ExecuteActivity.options = {"TaskQueueName":"q","ScheduleToCloseTimeout":0,"ScheduleToStartTimeout":0,"StartToCloseTimeout":5000000000,"HeartbeatTimeout":0,"WaitForCancellation":false,"ActivityID":"","RetryPolicy":{"initial_interval":{"seconds":2,"nanos":0},"backoff_coefficient":2,"maximum_interval":null,"maximum_attempts":3,"non_retryable_error_types":[]},"Priority":{"priority_key":0,"fairness_key":"","fairness_weight":0},"Summary":""}
ExecuteLocalActivity.options = {"ScheduleToCloseTimeout":0,"StartToCloseTimeout":5000000000,"RetryPolicy":null,"Summary":""}
ExecuteChildWorkflow.options = {"Namespace":"default","WorkflowID":"x","TaskQueueName":"default","WorkflowExecutionTimeout":0,"WorkflowRunTimeout":0,"WorkflowTaskTimeout":0,"WaitForCancellation":false,"WorkflowIDReusePolicy":2,"RetryPolicy":null,"CronSchedule":null,"ParentClosePolicy":1,"Memo":{"a":1},"SearchAttributes":{"k":"v"},"StaticDetails":"","StaticSummary":"","Priority":{…}}
ContinueAsNew.options.options = {"WorkflowRunTimeout":0,"TaskQueueName":"default","WorkflowTaskTimeout":0}
SideEffect.options = {"summary":"s"} (or [] → {})
```

`WaitForCancellation` is a bool. PHP `WAIT_CANCELLATION_COMPLETED` → `true`, `TRY_CANCEL` → `false`, other values throw (`PHP/Internal/Marshaller/Type/ActivityCancellationType.php:18-28`, `ChildWorkflowCancellationType.php:19-29`).

| PHP command (`name`) | options | payloads / header | RR / sdk-go action (`RRT/aggregatedpool/handler.go`) | Reply to PHP (id = request id) |
|---|---|---|---|---|
| `ExecuteActivity` | `{"name","options":ExecuteActivityOptions}` | args, header | `env.ExecuteActivity`. An empty task queue = the workflow task queue (`protocol.go:362-375`). Canceller registered (`:144-153`). | Success payloads on completion. Failure = `ActivityError`. Cancel with `WaitForCancellation=false` → **plain `CanceledError`** at once (`GO/internal_event_handlers.go:851-863`). With `true` → `ActivityError{cause: Canceled}` on the `ActivityTaskCanceled` event (`:1651-1676`). |
| `ExecuteLocalActivity` | `{"name","options":{"ScheduleToCloseTimeout","StartToCloseTimeout","RetryPolicy"(proto snake),"Summary"}}` | args, header | Both timeouts default to **1 min** if 0 (`protocol.go:378-417`). `env.ExecuteLocalActivity`. sdk-go does retries/backoff. | payloads, or failure (`handler.go:539-568`) |
| `ExecuteChildWorkflow` | `{"name","options":WorkflowOptions}` | input, header | Empty `WorkflowID` → `"<currentRunID>_<n>"` with a per-run counter (`handler.go:169-173`). `env.ExecuteChildWorkflow` (`GO/internal_event_handlers.go:565-640`): `Memo` and `SearchAttributes` values are encoded with the DC. | Resolves when the **child closes**: payloads, or `ChildWorkflowExecutionError`. A start failure calls both callbacks with `ChildWorkflowExecutionAlreadyStartedError`. |
| `GetChildWorkflowExecution` | `{"id": <ExecuteChildWorkflow request id>}` | – | `ids.Listen` — fires on child start or start failure, before or after registration (`RRT/registry/registry.go`) | Success payload = JSON `{"ID":wfId,"RunID":runId}` (`handler.go:184-200`), or failure |
| `NewTimer` | `{"ms":int,"summary"?:string}` | – | `env.NewTimer`. `ms==0` → **resolves at once, no command**. `<0` → error (`GO/internal_event_handlers.go:908-931`). | Success (no payloads) on fire. Cancel → `CanceledError` at once (`:933-943`). |
| `GetVersion` | `{"changeID","minSupported","maxSupported"}` | – | `env.GetVersion` (`GO/internal_event_handlers.go:958-1002`): cached per changeID. Replay without marker → `-1`. Not replaying → max (or PreferredVersionProvider), `Version` marker + `TemporalChangeVersion` SA `"<id>-<v>"`. `validateVersion` panics outside [min,max]. | **Synchronous**: success `[version int]`, then flush (`handler.go:215-232`) |
| `SideEffect` | `{"summary"?}` | `[value]`. PHP runs the closure only when not replaying (`WorkflowContext.php:303-336`). | `env.SideEffect`: records a `SideEffect` marker. Replay returns the recorded value (`GO/internal_event_handlers.go:1054-1090`). | **Synchronous**: success = recorded payloads, flush (`handler.go:234-242,603-623`) |
| `CompleteWorkflow` | `{}` | `[result]` or empty. `failure` set = workflow error. | `env.Complete(payloads, nil)` or `Complete(nil, FailureToError)` (`handler.go:282-292`). sdk-go: `CanceledError` → **`CancelWorkflowExecution`** (independent of a cancel request), `ContinueAsNewError` → CAN, else `FailWorkflowExecution` (`GO/internal_task_handlers.go:1884-1935`). | `"completed"` queued (sent with the next flush, normally dropped) |
| `ContinueAsNew` | `{"name","options":{"TaskQueueName","WorkflowRunTimeout","WorkflowTaskTimeout"}}` | args, header | `ContinueAsNewError`. Memo, SA, and retry policy are copied from the current run (`handler.go:294-308`, `GO/internal_task_handlers.go:1896-1925`). | `"completed"` |
| `UpsertWorkflowSearchAttributes` | `{"searchAttributes":{name: rawValue}}` | – | `env.UpsertSearchAttributes` (DC-encoded, no `type` metadata) | none (PHP sends it with `waitResponse=false`, `WorkflowContext.php:609`) |
| `UpsertWorkflowTypedSearchAttributes` | `{"search_attributes":{name:{"type","operation":"set"|"unset","value"?}}}` (`PHP/Internal/Transport/Request/UpsertTypedSearchAttributes.php`) | – | Typed updates. Payload metadata `type` = `IndexedValueType.String()` e.g. `Keyword` (`handler.go:317-481`, `GO/internal_search_attributes.go:398-416`). | none |
| `UpsertMemo` | `{"memo":{k: value|null}}` | – | `env.UpsertMemo` (null = delete) (`handler.go:521-530`) | none |
| `SignalExternalWorkflow` | `{"namespace","workflowID","runID"|null,"signal","childWorkflowOnly":bool}` | args (no header in PHP) | `env.SignalExternalWorkflow` (`handler.go:483-495`). A child signal uses `runID=null`, `childWorkflowOnly=true` (`ChildWorkflowStub.php:110-126`). | Success (no payloads) or failure. **Not cancellable** (no canceller registered). |
| `CancelExternalWorkflow` | `{"namespace","workflowID","runID"}` | – | `env.RequestCancelExternalWorkflow` | Success or failure |
| `Cancel` | `{"ids":[requestId,…]}` | – | Runs the registered canceller for each id: activity/LA/child/timer (`handler.go:501-514`, `RRT/canceller/canceller.go`) | The synchronous cancel callbacks push first (timer, TRY_CANCEL activity: `CanceledFailure`). Then success `"completed"` for the `Cancel` id. Flush **inside the same WFT**. |
| `Panic` | `{}` | `failure` | Returns the failure as an error → sdk-go WFT panic → `WorkflowPanicPolicy` (`handler.go:516-519`) | – |
| `UndefinedResponse` | `{"message"}` | – | Panic "undefined response" (`workflow.go:321-325`) | – |
| `UpdateValidated`/`UpdateCompleted` | `{"id":updateId}` | failure / `[result]` | See §2 | – |

PHP local shortcut: if a request is still in the outgoing queue when its scope is cancelled, PHP removes it and rejects it locally with `CanceledFailure('internal cancel')`, and sends **no** `Cancel` (`Scope.php:380-401`, `Client.php:96-105`). Non-cancellable requests (`CompleteWorkflow`, `ContinueAsNew`, `Panic`, children with `ParentClosePolicy::Abandon` unless a flag is set) send nothing.

---

## 4. Mapping to sdk-core

Core protos: activation `CP/workflow_activation/workflow_activation.proto`, commands `CP/workflow_commands/workflow_commands.proto`, completion `CP/workflow_completion/workflow_completion.proto`.

### 4.1 Activation header → context/TickInfo

| Core `WorkflowActivation` (`workflow_activation.proto:66-105`) | PHP |
|---|---|
| `run_id` | process key (use it as `ServerRequest` id / `Message.run_id`; it is also the core cache key) |
| `timestamp` | `tickTime` (core has ns precision; RR truncates to seconds) |
| `is_replaying` | `replay` |
| `history_length` | `history_length` |
| `history_size_bytes` | `history_size` |
| `continue_as_new_suggested` | `continue_as_new_suggested` |
| (not in activation) | `taskQueue` = the task queue of the core worker that polled |
| `available_internal_flags` | not used by PHP. Return an empty `used_internal_flags`. |
| `deployment_version_for_current_task`, `last_sdk_version`, `target_worker_deployment_version_changed`, `suggest_continue_as_new_reasons` | no PHP equivalent today |

### 4.2 Jobs → PHP server requests / responses

`seq` = the lang counter (uint32) that the adapter assigns when it translates a PHP request into a core command.

| Core job | PHP message | Notes / gaps |
|---|---|---|
| `InitializeWorkflow` (`:150-214`) | `StartWorkflow` request (`options.info` from the table in §4.3; payloads = `arguments` + `last_completion_result.payloads`, `options.lastCompletion = count`; header = `headers`; `options.search_attributes` = typed SA derived from each payload's `metadata.type`) | `continued_failure` has no PHP field today (RR does not send it). `randomness_seed` is not used by PHP (PHP randomness = `sideEffect`, `WorkflowContext.php:739-742`). |
| `SignalWorkflow` (`:296-303`) | `InvokeSignal{runId, name: signal_name}`, payloads = `input`, header = `headers` | `identity` is dropped (as RR does) |
| `CancelWorkflow{reason}` (`:290-293`) | `CancelWorkflow{runId}` | The reason is dropped. PHP ends the workflow with `CompleteWorkflow(failure=CanceledFailure)` → map to `CancelWorkflowExecution{}`. |
| `DoUpdate` (`:332-350`) | `InvokeUpdate{updateId: id, runId, name, type:"validate_execute", replay: !run_validator}`, payloads = `input`, header = `headers` | Keep `id → protocol_instance_id`. Core **requires** accepted/rejected in the same activation (`workflow_commands.proto:332-355`). PHP validates synchronously in `handle()`, so this holds. |
| `QueryWorkflow` (`:277-287`) | `InvokeQuery{runId, name: query_type}`, payloads = `arguments`, header = `headers` | Always in its own activation (`:115-117`). `query_id == "legacy"` → only `RespondToQuery` is allowed. PHP responses carry `id = runId` for **all** queries → dispatch one query per PHP cycle, as RR does. |
| `FireTimer{seq}` | `SuccessResponse(id = php id of seq)`, no payloads | – |
| `ResolveActivity{seq, result, is_local}` | completed → `SuccessResponse([result])`. failed → `FailureResponse(failure)`. cancelled → `FailureResponse(failure)`. backoff (LA only) → **not for PHP**: adapter-internal retry (§4.6). | Core cancel failure = `ActivityFailure{cause: CanceledFailure}` (`CORE/worker/workflow/machines/activity_state_machine.rs:139-165`). RR TRY_CANCEL gives a plain `CanceledFailure` (`GO/internal_event_handlers.go:859-861`). Also see the SideEffect emulation (§4.5). |
| `ResolveChildWorkflowExecutionStart{seq, succeeded{run_id} / failed{workflow_id,workflow_type,cause} / cancelled{failure}}` | Resolve the pending `GetChildWorkflowExecution` request with `SuccessResponse([{"ID":<workflow_id from the command>,"RunID":run_id}])`. On failed/cancelled: `FailureResponse` for **both** `GetChildWorkflowExecution` and `ExecuteChildWorkflow` (no `ResolveChildWorkflowExecution` follows). | Store the result per seq, because `GetChildWorkflowExecution` can arrive later (RR `registry.go:23-43`). Synthesize the "already started" failure from `cause`. |
| `ResolveChildWorkflowExecution{seq, result}` | completed → `SuccessResponse([result])`. failed/cancelled → `FailureResponse(failure)` for the `ExecuteChildWorkflow` id | – |
| `ResolveSignalExternalWorkflow{seq, failure?}` | success (no payloads) or failure | – |
| `ResolveRequestCancelExternalWorkflow{seq, failure?}` | success or failure | – |
| `NotifyHasPatch{patch_id}` | **no PHP message.** Adapter state for `GetVersion` (§4.5). | Sent pre-emptively from the look-ahead of the next WFT (`CORE/worker/workflow/machines/workflow_machines.rs:762-800`). Accumulate for the whole run (`PY:817-820`, `TS/internals.ts:1133-1137`). |
| `UpdateRandomSeed` | none (PHP has no seeded RNG) | – |
| `RemoveFromCache{message, reason}` | `DestroyWorkflow{runId}`. Complete the activation with empty commands. | Always alone in its activation (`:61-65,142-145`) |
| `ResolveNexusOperationStart/ResolveNexusOperation` | out of scope for the RR protocol (no nexus commands in `protocol.go`) | – |

### 4.3 `InitializeWorkflow` → `options.info` (PHP `WorkflowInfo` keys)

| PHP key | Core source |
|---|---|
| `WorkflowExecution` | `{ID: workflow_id, RunID: activation.run_id}` |
| `WorkflowType` | `{Name: workflow_type}` |
| `TaskQueueName` | **not in core** → worker task queue |
| `Namespace` | **not in core** → worker namespace |
| `WorkflowExecutionTimeout`/`WorkflowRunTimeout`/`WorkflowTaskTimeout` | the durations → int ns (0 if unset) |
| `Attempt` | `attempt` |
| `CronSchedule` | `cron_schedule` |
| `ContinuedExecutionRunID` | `continued_from_execution_run_id` |
| `FirstRunID` | `first_execution_run_id` |
| `OriginalRunID` | **not in core** → `run_id` |
| `ParentWorkflowNamespace` / `ParentWorkflowExecution` | `parent_workflow_info.namespace` / `{ID: workflow_id, RunID: run_id}` |
| `RootWorkflowExecution` | `root_workflow` (`{workflow_id, run_id}` is accepted by `WorkflowExecution.php:29-39`) |
| `Memo` | `memo` as proto JSON `{"fields":…}` |
| `SearchAttributes` | `search_attributes` as proto JSON `{"indexed_fields":…}` |
| `search_attributes` (typed, outside `info`) | from `search_attributes.indexed_fields[*].metadata.type` (`Text`→`string`, `Keyword`→`keyword`, `Int`→`int64`, `Double`→`float64`, `Bool`→`bool`, `Datetime`→`datetime` RFC3339, `KeywordList`→`keyword_list`) |
| `RetryPolicy` | `retry_policy` (proto snake JSON is accepted) |
| `Priority` | `priority` |
| `BinaryChecksum` | not in core → `""` or the deployment build id |
| (unused by PHP) | `identity`, `workflow_execution_expiration_time`, `cron_schedule_to_schedule_interval`, `start_time`, `continued_initiator`, `continued_failure` |

### 4.4 PHP commands → core `WorkflowCommand`s

The `ArrayQueue` order must be kept, because core matches commands to history events in order. `user_metadata` (`workflow_commands.proto:22-26`) holds summaries.

| PHP command | Core command | Mapping details |
|---|---|---|
| `NewTimer{ms,summary}` | `StartTimer{seq, start_to_fire_timeout}` + `user_metadata.summary` | `ms==0` → resolve at once without a command (RR parity). `<0` → failure. TS clamps to ≥1 ms (`TS/workflow.ts:183`). |
| `ExecuteActivity` | `ScheduleActivity{seq, activity_id: ActivityID ?: (string)seq, activity_type: name, task_queue: TaskQueueName ?: current, headers, arguments, schedule_to_close/schedule_to_start/start_to_close/heartbeat (0 = unset), retry_policy, cancellation_type: WaitForCancellation ? WAIT_CANCELLATION_COMPLETED(1) : TRY_CANCEL(0), priority}` + `user_metadata.summary = Summary` | PHP enum values ≠ core values (`PHP/Activity/ActivityCancellationType.php:32-68` vs `workflow_commands.proto:147-157`). Use the bool. TS default `activityId = "${seq}"` (`TS/workflow.ts:209`). |
| `ExecuteLocalActivity` | `ScheduleLocalActivity{seq, activity_id, activity_type, attempt:1, headers, arguments, schedule_to_close, start_to_close, retry_policy, cancellation_type: WAIT_CANCELLATION_COMPLETED}` | RR defaults: 1 min for both timeouts (`protocol.go:379-384`). `local_retry_threshold` is not set (core default 1 min). DoBackoff: see §4.6. |
| `ExecuteChildWorkflow` | `StartChildWorkflowExecution{seq, namespace, workflow_id: WorkflowID ?: "<runId>_<n>", workflow_type, task_queue, input, 3 timeouts, parent_close_policy (PHP 0..3 = core values), workflow_id_reuse_policy (API enum ints), retry_policy, cron_schedule, headers, memo (DC-encode each value), search_attributes (DC-encode), cancellation_type, priority}` + `user_metadata{summary: StaticSummary, details: StaticDetails}` | Core `cancellation_type` proto default is `ABANDON=0` (`child_workflow.proto`). Always set it. RR ignores `WaitForCancellation` at env level (`GO/internal_event_handlers.go:80,640` store it only), so under RR the result settles when the child closes → `WAIT_CANCELLATION_COMPLETED(2)` keeps RR behaviour. |
| `GetChildWorkflowExecution{id}` | **no command.** Wait for `ResolveChildWorkflowExecutionStart` of the child seq. | – |
| `SignalExternalWorkflow` | `SignalExternalWorkflowExecution{seq, workflow_execution{namespace, workflow_id, run_id?} or child_workflow_id (if childWorkflowOnly), signal_name, args, headers}` | – |
| `CancelExternalWorkflow` | `RequestCancelExternalWorkflowExecution{seq, workflow_execution{namespace, workflow_id, run_id}}` | – |
| `Cancel{ids}` | per id: timer → `CancelTimer{seq}` **and** synthesize `FailureResponse(CanceledFailure)` for the timer id (core sends no job for a cancelled timer, `timer_state_machine.rs:92-102`). Activity → `RequestCancelActivity{seq}` (core pushes the resolution itself if not WAIT_COMPLETED, `activity_state_machine.rs:139-151`). LA → `RequestCancelLocalActivity{seq}` (or `CancelTimer` of the backoff timer). Child → `CancelChildWorkflowExecution{child_workflow_seq}`. Signal-external → nothing (RR parity; core has `CancelSignalWorkflow`). Then `SuccessResponse(["completed"])` for the `Cancel` id, fed back to PHP in the same activation. | RR order: cancel-callback failures **before** the `Cancel` success. |
| `GetVersion` | `SetPatchMarker` (see §4.5). Reply `SuccessResponse([version])` locally and re-dispatch in the same activation. | Go `Version` markers ≠ core `core_patch` markers |
| `SideEffect` | no core command (§4.5) | – |
| `CompleteWorkflow` success | `CompleteWorkflowExecution{result: payloads[0] or unset}` | Core `result` is **one** Payload (`:183-185`) |
| `CompleteWorkflow` failure = `CanceledFailure` | `CancelWorkflowExecution{}` | sdk-go does this for any `CanceledError` (`GO/internal_task_handlers.go:1890-1896`). PY/TS only when a cancel was requested (`PY:2646-2656`, `TS/internals.ts:1220-1222`). |
| `CompleteWorkflow` other failure | `FailWorkflowExecution{failure}` | – |
| `ContinueAsNew` | `ContinueAsNewWorkflowExecution{workflow_type, task_queue, arguments, workflow_run_timeout, workflow_task_timeout, headers}`. Leave memo/SA/retry unset → core reuses the current values (`:192-222`). | Same as sdk-go |
| `UpsertWorkflowSearchAttributes` | `UpsertWorkflowSearchAttributes{search_attributes.indexed_fields: DC-encoded}` | – |
| `UpsertWorkflowTypedSearchAttributes` | same command, payload `metadata.type` = `Bool`/`Double`/`Int`/`Keyword`/`KeywordList`/`Text`/`Datetime`. unset → an encoded null without `type` (Go `serializeTypedSearchAttributes`). | – |
| `UpsertMemo` | `ModifyWorkflowProperties{upserted_memo.fields}` | null value: core says "default/empty Payload" deletes (`:325-330`). Go encodes nil with the DC. Test which one the server accepts. |
| `UpdateValidated{id}` | `UpdateResponse{protocol_instance_id, accepted:{}}` or `rejected: failure` | – |
| `UpdateCompleted{id}` | `UpdateResponse{…, completed: payloads[0]}` or `rejected: failure` | – |
| `Panic` | `WorkflowActivationCompletion.failed{failure}` | WFT failure. `WorkflowPanicPolicy=FailWorkflow` (WorkerOptions) would need `FailWorkflowExecution`. |
| `UndefinedResponse` | `failed{failure(message)}` | – |
| responses to server requests (`SuccessClientResponse`/`FailedClientResponse` with string id) | ignored, except for `InvokeQuery` → `RespondToQuery{query_id, succeeded.response = payloads[0] / failed}` | – |

### 4.5 Semantic gaps

**GetVersion (Go style) vs core patches.**
* Core records marker `core_patch` with details `patch_id` + `deprecated` (`CORE/.../patch_state_machine.rs:95-109`, `common/src/protos/constants.rs:4`). It only stores **presence**. Go records marker `Version` with `change-id` + `version` (`GO/internal_command_state_machine.go:231-234,1284-1300`). **History is not compatible in either direction.** Core rejects a Go `Version`/`SideEffect`/`LocalActivity` marker with `nondeterminism` ("No command scheduled for event", `workflow_machines.rs:991-999`; patch handling `:1735-1789`).
* Emulation that keeps the version number: patch id = `"<changeId>-<version>"`. This is also the exact Go `TemporalChangeVersion` value format (`GO/internal_event_handlers.go:1050-1052`), and core upserts `TemporalChangeVersion` from the patch ids (`patch_state_machine.rs:118-140`). Per run and changeId:
  1. If a cached version exists → validate [min,max] and return it (Go `:959-962`).
  2. If a notified patch `"<changeId>-N"` exists → version N. Emit `SetPatchMarker{patch_id:"<changeId>-N"}` (core requires the command when history has a non-deprecated marker).
  3. Else if replaying → `-1` (DEFAULT_VERSION), no command.
  4. Else → `max`. Emit `SetPatchMarker{"<changeId>-max"}`.
  5. Validate [min,max]. Outside the range → fail the activation (Go panics, `:945-956`).
* Go tolerates a removed `GetVersion` call. Core tolerates a missing patch call only for markers with `deprecated=true` (`workflow_machines.rs:1760-1776`). Writing `deprecated:true` on every `SetPatchMarker` emulates Go's tolerance. This is not tested here.

**SideEffect.** Core has no generic marker command (the command list is `workflow_commands.proto:28-51`). TS, Python, and .NET have no side effect and use seeded randomness plus local activities. The durable emulation that fits core is an **echo local activity**: `ScheduleLocalActivity{type:"__php_side_effect", arguments:[value]}`. The adapter completes the LA task itself with `result = arguments[0]`, without PHP user code. On replay, core resolves it from the `core_local_activity` marker and does not run it. PHP gets `SuccessResponse(result)` from `ResolveActivity`. Costs: the result comes one activation later (not synchronous as in RR), each call adds a marker, and `Workflow::uuid*()` uses `sideEffect` (`WorkflowContext.php:739-750`). An alternative is to change `uuid()`/random to use `randomness_seed`. That is an API behaviour change.

**Local activities.** Core `ResolveActivity.backoff{attempt, backoff_duration, original_schedule_time}` (`activity_result.proto:53-61`) asks lang to start a timer and reschedule. PHP has no such concept → the adapter does it: internal `StartTimer` (timer seq), then on `FireTimer` → a new `ScheduleLocalActivity` with a **new seq**, the same type/args/options, `attempt`, and `original_schedule_time` (TS `workflow.ts:366-399`, PY `:1937-1951,3208-3297`). Keep the PHP request id → current seq. A PHP `Cancel` during the backoff → `CancelTimer` + synthesize the cancel failure.

**Queries.** A legacy query (`query_id=="legacy"`) must get only `RespondToQuery`. Non-legacy queries come after all other jobs in their own activation. `__stack_trace` arrives as a normal `QueryWorkflow`. PHP `InvokeQuery` handles it (`InvokeQuery.php:17-23`). The RR `StackTrace` route is only for sdk-go.

**Updates.** On replay, core sets `run_validator=false`. The adapter must send `options.replay=true` so that PHP skips the validator (`InvokeUpdate.php:54-61`). RR never sends it. Accepted/rejected must be in the same completion. Completed/rejected can be in any later completion.

**Workflow cancel.** `CancelWorkflow` job → PHP cancels the root scope → `CompleteWorkflow(CanceledFailure)` → `CancelWorkflowExecution`. Only sdk-go emits the cancel command without a cancel request. To keep RR parity, map any `CanceledFailure` completion to `CancelWorkflowExecution`. Check that core accepts it without a cancel request.

**Eviction.** `RemoveFromCache` → `DestroyWorkflow`. RR also sends `DestroyWorkflow` after the run ends (`Close()`). Core sends `RemoveFromCache` with `WORKFLOW_EXECUTION_ENDING` (`workflow_activation.proto:403-405`).

### 4.6 Child workflow lifecycle (RR vs core)

| Event | RR/Go reply | Core job | Adapter |
|---|---|---|---|
| child started | `GetChildWorkflowExecution` ← `{"ID","RunID"}` | `ResolveChildWorkflowExecutionStart.succeeded{run_id}` | Needs the `workflow_id` from the command |
| start failed (already exists) | both requests ← `ChildWorkflowExecutionAlreadyStartedError` | `…Start.failed{workflow_id, workflow_type, cause}` | Build the failure for both requests |
| cancelled before start | result ← canceled | `…Start.cancelled{failure}` | Fail both requests |
| child closed | `ExecuteChildWorkflow` ← result/`ChildWorkflowExecutionError` | `ResolveChildWorkflowExecution` | – |

---

## 5. Activities

### 5.1 `ActivityTask.start` (`CP/activity_task/activity_task.proto:16-56`) → `InvokeActivity`/`InvokeLocalActivity`

| PHP `options` | Core |
|---|---|
| `name` | `activity_type` |
| `info.TaskToken` | base64(`task_token`) |
| `info.WorkflowType` | `{Name: workflow_type}` |
| `info.WorkflowNamespace` | `workflow_namespace` |
| `info.WorkflowExecution` | `{ID: workflow_execution.workflow_id, RunID: workflow_execution.run_id}` |
| `info.ActivityID` / `info.ActivityType` | `activity_id` / `{Name: activity_type}` |
| `info.TaskQueue` | **not in core** → worker task queue |
| `info.HeartbeatTimeout` | `heartbeat_timeout` (ns) |
| `info.ScheduledTime` / `info.StartedTime` | `scheduled_time` / `started_time` (RFC3339) |
| `info.Deadline` | **not in core** → compute like Go: min(`scheduled_time + schedule_to_close`, `started_time + start_to_close`) |
| `info.Attempt` / `info.Priority` / `info.RetryPolicy` | `attempt` / `priority` / `retry_policy` |
| `heartbeatDetails` + payloads | `heartbeat_details` count, appended after `input` |
| header | `header_fields` |
| command | `is_local ? InvokeLocalActivity : InvokeActivity` |
| context | `{"taskQueue": <worker task queue>}` |

### 5.2 Result → `ActivityTaskCompletion{task_token, result}` (`CP/activity_result/activity_result.proto:3-10`)

| PHP reply | Core `ActivityExecutionResult` |
|---|---|
| success `[result]` | `completed{result: payloads[0]}` |
| failure with message `doNotCompleteOnReturn` | `will_complete_async{}` (not valid for a local activity) |
| failure `CanceledFailure`/`ActivityCanceledException` after a core `Cancel` task | `cancelled{failure: CanceledFailure}` (Go uses `RespondActivityTaskCanceled` for a canceled ctx + `CanceledError`) |
| other failure | `failed{failure}` |

### 5.3 Heartbeat

* PHP: `rpc->call('temporal.RecordActivityHeartbeat', {"taskToken": b64(bytes), "details": b64(Payloads protobuf bytes)})` (`PHP/Internal/Activity/ActivityContext.php:113-151`). Response keys `canceled`, `paused`, `reset` → throw `ActivityCanceledException`/`Paused`/`Reset`.
* RR: `RecordHeartbeatRequest{taskToken []byte, details []byte}` → `activity.RecordHeartbeat(ctx, details)` (sdk-go throttles). Response `{canceled, paused}` from `ctx.Done()`/`ErrActivityPaused` (`RRT/rpc.go:37-96`). RR never returns `reset`. An unknown token → error `heartbeat on non running activity` (`RRT/aggregatedpool/activity.go:52-60`).
* Core: `record_activity_heartbeat(ActivityHeartbeat{task_token, details: repeated Payload})` is non-blocking. Errors are only logged (`CORE/worker/mod.rs:1180-1193`). Cancellation comes only as `ActivityTask{task_token, cancel{reason, details{is_cancelled,is_paused,is_reset,is_timed_out,is_worker_shutdown,is_not_found}}}` from `poll_activity_task` (`activity_task.proto:57-85`). Lang must still complete the task.
* Adapter: the native side must poll activity tasks in the background, store `Cancel` per token, and **queue** new `Start` tasks. PHP runs activities synchronously, so a heartbeat is the only point where it can see a cancel. `heartbeat()` = `record_activity_heartbeat` + read the stored cancel flag → `{canceled: is_cancelled|timed_out|worker_shutdown|not_found, paused: is_paused, reset: is_reset}`. Decode `details` (protobuf `Payloads`) → `repeated Payload`.

---

## 6. Job ordering

| | Order |
|---|---|
| Core (`workflow_activation.proto:25-59`) | init → patches → random seed → signals/updates → others (timers, activity/child/external resolutions, CancelWorkflow) → LA resolutions → queries (own activation) → eviction (own activation). The same order on replay. |
| RR/sdk-go frame (§1.4) | StartWorkflow → signals + CancelWorkflow (history order) → responses (history order, LA included) → InvokeUpdate. Plus extra same-WFT round trips after `GetVersion`/`SideEffect`/`Cancel`. |
| PY (`PY:465-492`) | notify_has_patch → signals+updates → others (incl. init) → queries. One loop iteration after each set. |

Determinism needs only a **fixed** order, because core gives the same jobs on replay. To keep PHP behaviour close to RR, reorder inside one activation: `InitializeWorkflow` → `SignalWorkflow`+`CancelWorkflow` (core order) → resolutions (core order, LA last) → `DoUpdate`, then one `tick()`. Then run extra PHP cycles in the same activation while PHP emits sync commands (`GetVersion`, `Cancel` of timers, local responses). Queries: one per cycle.

---

## 7. Adapter state and RR assumptions

### 7.1 Per-run state

* `run_id` → process (PHP `ProcessCollection` key = `ServerRequest` id).
* seq counters: `timer`, `activity` (shared by activity + LA, as `TS/internals.ts:411-420`), `childWorkflow`, `signalExternal`, `cancelExternal`. Core `seq` is `uint32`, PHP ids are int64 → two-way maps `phpRequestId ↔ (kind, seq)`.
* child: seq → `{workflow_id, start result (stored), phpExecuteId, phpGetExecutionId?}`. Per-run child counter for the default id `"<runId>_<n>"`.
* timers: seq → phpId. Remember cancelled ones to synthesize `CanceledFailure`.
* LA: phpId → `{seq, attempt, original_schedule_time, backoff timer seq, the original ScheduleLocalActivity}`.
* SideEffect echo-LA: phpId → seq.
* GetVersion: `changeId → version` cache, the set of notified patch ids (whole run).
* updates: `updateId → protocol_instance_id`.
* the cancel-requested flag.
* a buffer of commands in PHP order, then one terminal command last.

### 7.2 Worker-level state

* task token → cancel details (background activity poller). A queue of `Start` tasks received during a heartbeat.
* task queue + namespace per core worker (not in `InitializeWorkflow`/`Start`).

### 7.3 PHP code that assumes RoadRunner

| Item | Location | Core replacement |
|---|---|---|
| `GetWorkerInfo` handshake (task queues, `WorkerOptions`, workflows with `versioning_behavior`, activities, `PhpSdkVersion`, `Flags.ApiKey`) | `PHP/WorkerFactory.php:304-313`, `Router/GetWorkerInfo.php` | Call the route or read `WorkerFactory` queues to build core `WorkerConfig`. Map `WorkerOptions` (`PHP/Worker/WorkerOptions.php` keys `MaxConcurrent*`, `*PerSecond`, `StickyScheduleToStartTimeout`, `WorkflowPanicPolicy`, `DeadlockDetectionTimeout`, `MaxHeartbeatThrottleInterval`, `DisableEagerActivities`, `BuildID`, `UseBuildIDForVersioning`, `DeploymentOptions`, …). `versioning_behavior` → `Success.versioning_behavior`. |
| Default `RoadRunner::create()` / `Goridge::create()` | `WorkerFactory.php:158,259`, `RoadRunner.php`, `RoadRunnerVersionChecker.php` | Pass the adapter host and the adapter RPC |
| `RR_CODEC` env | `WorkerFactory.php:353-361` | Set it, or bypass the codec |
| RPC `temporal.RecordActivityHeartbeat` | `ActivityContext.php:123-129` | §5.3 |
| RPC `temporal.UpdateAPIKey` (user doc) | `PHP/Worker/ServiceCredentials.php:30-45`, `RRT/rpc.go:421` | core client API key update |
| RPC `temporal.ReplayWorkflowHistory`, `ReplayWorkflow`, `DownloadWorkflowHistory`, `ReplayFromJSON` | `testing/src/Replay/WorkflowReplayer.php:55-120`, `RRT/rpc.go:118-420` | core replayer (`temporal_core_worker_replayer_new`, `…_replay_push` in `sdk-core-c-bridge/src/worker.rs:975-1034`) |
| RR KV plugin (activity/child/SA mock caches for tests) | `PHP/Worker/ActivityInvocationCache/RoadRunnerActivityInvocationCache.php`, `ChildWorkflowInvocationCache/…`, `SearchAttributeInvocationCache/…`, `testing/src/WorkerFactory.php:40-62` | Out of the protocol. It needs its own store. |
| sdk-go features that PHP gets for free from RR | deadlock detection, `WorkflowPanicPolicy`, LA retry/backoff, `TemporalChangeVersion` upsert, default child id, SDK name `temporal-php-2` + version header (`RRT/plugin.go:43-45`, `internal.go:228-241`) | Must be done in the adapter/core config |
| Transport of results: RR ignores string-id responses. Queries/activities need exactly one reply per frame. | `Encoder.php:65,71`, `handler.go:733`, `activity.go:141` | Keep one query / one activity per PHP cycle |

C bridge entry points: `temporal_core_worker_poll_workflow_activation`, `…_complete_workflow_activation`, `…_poll_activity_task`, `…_complete_activity_task`, `…_record_activity_heartbeat`, `…_request_workflow_eviction`, `…_initiate_shutdown`, `…_finalize_shutdown` (`sdk-core-c-bridge/src/worker.rs:664-938`).
