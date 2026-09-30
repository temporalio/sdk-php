use prost::Message;
use std::{
    collections::HashMap,
    process::Command,
    time::{Duration, Instant},
};
use temporal_php_bridge::*;
use temporalio_common::protos::{
    coresdk::{
        ActivityTaskCompletion, AsJsonPayloadExt,
        activity_result::{ActivityExecutionResult, activity_resolution},
        activity_task::{ActivityTask, activity_task},
        workflow_activation::{WorkflowActivation, workflow_activation_job::Variant as Job},
        workflow_commands::{
            CompleteWorkflowExecution, ScheduleActivity, workflow_command::Variant as Cmd,
        },
        workflow_completion::WorkflowActivationCompletion,
    },
    temporal::api::common::v1::Payload,
};

const CLI: &str = "/Users/xepozz/IdeaProjects/temporalio/sdk-php-fiber-runtime/temporal";
const ADDRESS: &str = "127.0.0.1:7555";
const QUEUE: &str = "smoke-q";
const POLL_WORKFLOW: u64 = 1;
const POLL_ACTIVITY: u64 = 2;

struct Event {
    tag: u64,
    kind: i32,
    status: i32,
    data: Vec<u8>,
}

fn cli(args: &[&str]) -> String {
    let out = Command::new(CLI)
        .args(args)
        .args(["--address", ADDRESS])
        .output()
        .unwrap();
    String::from_utf8_lossy(&out.stdout).into_owned() + &String::from_utf8_lossy(&out.stderr)
}

fn next_events(rt: *mut TpbRuntime) -> Vec<Event> {
    let mut buf: Vec<TpbEvent> = (0..16)
        .map(|_| TpbEvent {
            tag: 0,
            kind: 0,
            status: 0,
            data: std::ptr::null_mut(),
            len: 0,
        })
        .collect();
    let count = tpb_next_events(rt, 30_000, buf.as_mut_ptr(), buf.len());
    assert!(count > 0, "no events within 30s");
    buf.truncate(count);
    buf.into_iter()
        .map(|e| {
            let data = if e.data.is_null() {
                Vec::new()
            } else {
                unsafe { std::slice::from_raw_parts(e.data, e.len) }.to_vec()
            };
            tpb_bytes_free(e.data, e.len);
            Event {
                tag: e.tag,
                kind: e.kind,
                status: e.status,
                data,
            }
        })
        .collect()
}

fn payload(text: &str) -> Payload {
    text.as_json_payload().unwrap()
}

fn respond(
    activation: &WorkflowActivation,
    started: &mut HashMap<String, String>,
) -> (WorkflowActivationCompletion, String) {
    let run_id = activation.run_id.clone();
    for job in activation.jobs.iter().filter_map(|j| j.variant.as_ref()) {
        match job {
            Job::RemoveFromCache(_) => {
                return (
                    WorkflowActivationCompletion::empty(run_id),
                    "eviction".into(),
                );
            }
            Job::InitializeWorkflow(init) => {
                started.insert(run_id.clone(), init.workflow_id.clone());
                if init.workflow_id == "smoke-2" {
                    let schedule = ScheduleActivity {
                        seq: 1,
                        activity_id: "1".into(),
                        activity_type: "SmokeAct".into(),
                        task_queue: QUEUE.into(),
                        start_to_close_timeout: Some(Duration::from_secs(10).try_into().unwrap()),
                        ..Default::default()
                    };
                    return (
                        WorkflowActivationCompletion::from_cmd(
                            run_id,
                            Cmd::ScheduleActivity(schedule),
                        ),
                        "smoke-2 schedule activity".into(),
                    );
                }
                let done = Cmd::CompleteWorkflowExecution(CompleteWorkflowExecution {
                    result: Some(payload("ok")),
                });
                return (
                    WorkflowActivationCompletion::from_cmd(run_id, done),
                    format!("{} complete workflow", init.workflow_id),
                );
            }
            Job::ResolveActivity(resolve) => {
                let result = match resolve.result.as_ref().and_then(|r| r.status.as_ref()) {
                    Some(activity_resolution::Status::Completed(ok)) => ok.result.clone(),
                    other => panic!("unexpected activity resolution {other:?}"),
                };
                let done = Cmd::CompleteWorkflowExecution(CompleteWorkflowExecution { result });
                return (
                    WorkflowActivationCompletion::from_cmd(run_id, done),
                    "smoke-2 complete workflow after activity".into(),
                );
            }
            _ => {}
        }
    }
    (WorkflowActivationCompletion::empty(run_id), "empty".into())
}

fn main() {
    for id in ["smoke-1", "smoke-2"] {
        cli(&[
            "workflow",
            "terminate",
            "--workflow-id",
            id,
            "--reason",
            "smoke restart",
        ]);
    }
    let rt = tpb_runtime_new();
    assert!(!rt.is_null());
    let config = format!(
        r#"{{"target_url":"http://{ADDRESS}","namespace":"default","task_queue":"{QUEUE}","max_cached_workflows":10}}"#
    );
    let mut err: *mut u8 = std::ptr::null_mut();
    let mut err_len = 0usize;
    let t = Instant::now();
    let w = tpb_worker_new(
        rt,
        config.as_ptr().cast(),
        config.len(),
        &mut err,
        &mut err_len,
    );
    if w.is_null() {
        panic!(
            "worker_new failed: {}",
            String::from_utf8_lossy(unsafe { std::slice::from_raw_parts(err, err_len) })
        );
    }
    println!(
        "worker created and validated in {:?}, event fd = {}",
        t.elapsed(),
        tpb_event_fd(rt)
    );

    tpb_poll_workflow_activation(w, POLL_WORKFLOW);
    tpb_poll_activity_task(w, POLL_ACTIVITY);

    let t = Instant::now();
    print!(
        "{}",
        cli(&[
            "workflow",
            "start",
            "--type",
            "SmokeWf",
            "--task-queue",
            QUEUE,
            "--workflow-id",
            "smoke-1"
        ])
    );
    print!(
        "{}",
        cli(&[
            "workflow",
            "start",
            "--type",
            "SmokeWf",
            "--task-queue",
            QUEUE,
            "--workflow-id",
            "smoke-2"
        ])
    );
    println!("both workflows started via CLI in {:?}", t.elapsed());

    let mut started = HashMap::new();
    let mut pending: HashMap<u64, (Instant, String)> = HashMap::new();
    let mut next_tag = 100u64;
    let mut finished = 0;
    let mut shutting_down = false;
    let mut finalize_requested = false;
    let (mut wf_poll_done, mut act_poll_done) = (false, false);
    let mut scheduled_at: Option<Instant> = None;

    loop {
        for e in next_events(rt) {
            match (e.kind, e.status) {
                (1, 0) => {
                    let activation = WorkflowActivation::decode(e.data.as_slice()).unwrap();
                    let (completion, label) = respond(&activation, &mut started);
                    if label == "smoke-2 schedule activity" {
                        scheduled_at = Some(Instant::now());
                    }
                    next_tag += 1;
                    pending.insert(next_tag, (Instant::now(), label));
                    let bytes = completion.encode_to_vec();
                    tpb_complete_workflow_activation(
                        w,
                        next_tag,
                        bytes.as_ptr().cast(),
                        bytes.len(),
                    );
                    tpb_poll_workflow_activation(w, POLL_WORKFLOW);
                }
                (2, 0) => {
                    let task = ActivityTask::decode(e.data.as_slice()).unwrap();
                    if let Some(at) = scheduled_at.take() {
                        println!(
                            "  activity task arrived {:?} after ScheduleActivity was sent",
                            at.elapsed()
                        );
                    }
                    let result = match task.variant {
                        Some(activity_task::Variant::Start(ref start)) => {
                            println!(
                                "  activity task: type={} workflow={}",
                                start.activity_type,
                                start.workflow_execution.as_ref().unwrap().workflow_id
                            );
                            ActivityExecutionResult::ok(payload("act-ok"))
                        }
                        other => panic!("unexpected activity task {other:?}"),
                    };
                    next_tag += 1;
                    pending.insert(next_tag, (Instant::now(), "activity complete".into()));
                    let completion = ActivityTaskCompletion {
                        task_token: task.task_token,
                        result: Some(result),
                    }
                    .encode_to_vec();
                    tpb_complete_activity_task(
                        w,
                        next_tag,
                        completion.as_ptr().cast(),
                        completion.len(),
                    );
                    tpb_poll_activity_task(w, POLL_ACTIVITY);
                }
                (3 | 4, 0) => {
                    let (at, label) = pending.remove(&e.tag).unwrap();
                    println!(
                        "  kind {} ack: {label}: activation->ack roundtrip {:?}",
                        e.kind,
                        at.elapsed()
                    );
                    if label.ends_with("complete workflow") || label.ends_with("after activity") {
                        finished += 1;
                    }
                }
                (1, 2) => wf_poll_done = true,
                (2, 2) => act_poll_done = true,
                (5, 0) => {
                    println!("finalize_shutdown done");
                    tpb_worker_free(w);
                    tpb_runtime_free(rt);
                    for id in ["smoke-1", "smoke-2"] {
                        let out = cli(&["workflow", "describe", "--workflow-id", id]);
                        let status = out
                            .lines()
                            .find(|l| l.trim_start().starts_with("Status"))
                            .unwrap_or("?")
                            .trim()
                            .to_owned();
                        let result = out
                            .lines()
                            .skip_while(|l| !l.contains("Results"))
                            .nth(3)
                            .unwrap_or("")
                            .trim()
                            .to_owned();
                        println!("{id}: {status} {result}");
                    }
                    print!("{}", cli(&["workflow", "show", "--workflow-id", "smoke-2"]));
                    return;
                }
                (kind, status) => panic!(
                    "unexpected event kind={kind} status={status}: {}",
                    String::from_utf8_lossy(&e.data)
                ),
            }
        }
        if finished == 2 && !shutting_down {
            shutting_down = true;
            println!("both workflows completed, initiating shutdown");
            tpb_worker_initiate_shutdown(w);
        }
        if shutting_down
            && !finalize_requested
            && wf_poll_done
            && act_poll_done
            && pending.is_empty()
        {
            finalize_requested = true;
            tpb_worker_finalize_shutdown(w, 999);
            println!("both polls returned ShutDown, finalize requested");
        }
    }
}
