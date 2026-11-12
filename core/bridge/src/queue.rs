use crate::ffi::{
    KIND_LOG, STATUS_ERROR, STATUS_OK, STATUS_SHUTDOWN, TpbEvent, call, events_out, into_raw_bytes,
    panic_message,
};
use crate::runtime::TpbRuntime;
use futures_util::FutureExt;
use prost::Message;
use serde_json::Value;
use std::{
    collections::{HashMap, VecDeque},
    future::Future,
    panic::AssertUnwindSafe,
    sync::{Arc, Condvar, Mutex, MutexGuard, PoisonError},
    time::Duration,
};
use temporalio_sdk_core::PollError;
use tokio::runtime::Handle;

pub(crate) const MAX_PENDING_LOGS: usize = 10_000;

struct Event {
    tag: u64,
    kind: i32,
    status: i32,
    data: Box<[u8]>,
}

#[derive(Default)]
struct Events {
    list: VecDeque<Event>,
    logs: usize,
    dropped_logs: u64,
}

impl Events {
    fn add(&mut self, tag: u64, kind: i32, status: i32, data: Vec<u8>) {
        if kind == KIND_LOG {
            self.logs += 1;
        }
        self.list.push_back(Event {
            tag,
            kind,
            status,
            data: data.into_boxed_slice(),
        });
    }
}

fn log_entry(level: &str, target: &str, message: &str, fields: &HashMap<String, Value>) -> Vec<u8> {
    serde_json::json!({
        "level": level,
        "target": target,
        "message": message,
        "fields": fields,
    })
    .to_string()
    .into_bytes()
}

pub struct Queue {
    pub handle: Handle,
    events: Mutex<Events>,
    ready: Condvar,
}

impl Queue {
    pub fn new(handle: Handle) -> Self {
        Self {
            handle,
            events: Mutex::new(Events::default()),
            ready: Condvar::new(),
        }
    }

    fn events(&self) -> MutexGuard<'_, Events> {
        self.events.lock().unwrap_or_else(PoisonError::into_inner)
    }

    pub fn push(&self, tag: u64, kind: i32, status: i32, data: Vec<u8>) {
        let mut events = self.events();
        events.add(tag, kind, status, data);
        self.wake(events);
    }

    pub fn push_log(
        &self,
        level: &str,
        target: &str,
        message: &str,
        fields: &HashMap<String, Value>,
    ) {
        let entry = log_entry(level, target, message, fields);
        let mut events = self.events();
        let dropped = events.dropped_logs;
        if events.logs + usize::from(dropped > 0) >= MAX_PENDING_LOGS {
            events.dropped_logs += 1;
            return;
        }
        if dropped > 0 {
            let notice = format!("{dropped} log records were dropped: the event queue was full");
            let notice = log_entry("WARN", env!("CARGO_CRATE_NAME"), &notice, &HashMap::new());
            events.add(0, KIND_LOG, STATUS_OK, notice);
            events.dropped_logs = 0;
        }
        events.add(0, KIND_LOG, STATUS_OK, entry);
        self.wake(events);
    }

    fn wake(&self, events: MutexGuard<'_, Events>) {
        drop(events);
        self.ready.notify_one();
    }

    pub fn push_error(&self, tag: u64, kind: i32, result: Result<(), String>) {
        if let Err(message) = result {
            self.push(tag, kind, STATUS_ERROR, message.into_bytes());
        }
    }

    pub fn push_poll<T: Message>(&self, tag: u64, kind: i32, result: Result<T, PollError>) {
        match result {
            Ok(message) => self.push(tag, kind, STATUS_OK, message.encode_to_vec()),
            Err(PollError::ShutDown) => self.push(tag, kind, STATUS_SHUTDOWN, Vec::new()),
            Err(err) => self.push(tag, kind, STATUS_ERROR, err.to_string().into_bytes()),
        }
    }

    pub fn spawn(
        self: &Arc<Self>,
        tag: u64,
        kind: i32,
        on_panic: fn(String) -> (i32, Vec<u8>),
        task: impl Future<Output = ()> + Send + 'static,
    ) {
        let queue = self.clone();
        let task = AssertUnwindSafe(task).catch_unwind();
        self.handle.spawn(async move {
            if let Err(panic) = task.await {
                let (status, data) = on_panic(panic_message(panic));
                queue.push(tag, kind, status, data);
            }
        });
    }

    fn take(&self, timeout_ms: u32, out: &mut [TpbEvent]) -> usize {
        let mut events = self.events();
        if events.list.is_empty() && timeout_ms > 0 {
            let timeout = Duration::from_millis(timeout_ms.into());
            events = self
                .ready
                .wait_timeout_while(events, timeout, |e| e.list.is_empty())
                .unwrap_or_else(PoisonError::into_inner)
                .0;
        }
        let events = &mut *events;
        let count = events.list.len().min(out.len());
        for (slot, event) in out.iter_mut().zip(events.list.drain(..count)) {
            if event.kind == KIND_LOG {
                events.logs -= 1;
            }
            let (data, len) = into_raw_bytes(event.data);
            *slot = TpbEvent {
                tag: event.tag,
                kind: event.kind,
                status: event.status,
                data,
                len,
            };
        }
        count
    }
}

pub fn error_status(message: String) -> (i32, Vec<u8>) {
    (STATUS_ERROR, message.into_bytes())
}

#[unsafe(no_mangle)]
pub unsafe extern "C" fn tpb_next_events(
    rt: *mut TpbRuntime,
    timeout_ms: u32,
    out: *mut TpbEvent,
    max: usize,
) -> usize {
    call(rt, 0, |rt| rt.queue.take(timeout_ms, events_out(out, max)))
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::ffi::{KIND_ACTIVITY_TASK, release};
    use crate::testing::{Checked, empty_events, events, only, runtime};
    use temporalio_common::protos::coresdk::activity_task::ActivityTask;

    #[test]
    fn take_fills_at_most_the_buffer_and_returns_at_once_without_timeout() {
        let rt = runtime();
        let queue = &unsafe { &*rt }.queue;
        let mut out: [TpbEvent; 2] = empty_events();
        assert_eq!(queue.take(0, &mut out), 0);

        for tag in 1..=3 {
            queue.push(tag, 0, STATUS_OK, Vec::new());
        }
        assert_eq!(queue.take(0, &mut out), 2);
        assert_eq!([out[0].tag, out[1].tag], [1, 2]);
        assert_eq!(queue.take(0, &mut out), 1);
        assert_eq!(out[0].tag, 3);
        release(rt);
    }

    #[test]
    fn null_runtime_has_no_events() {
        let mut out: [TpbEvent; 1] = empty_events();
        assert_eq!(
            unsafe { tpb_next_events(std::ptr::null_mut(), 0, out.as_mut_ptr(), 1) },
            0
        );
    }

    #[test]
    fn null_event_buffer_takes_no_events() {
        let rt = runtime();
        unsafe { &*rt }.queue.push(1, 0, STATUS_OK, Vec::new());

        for max in [0, 4] {
            assert_eq!(
                unsafe { tpb_next_events(rt, 0, std::ptr::null_mut(), max) },
                0
            );
        }
        assert_eq!(events(rt, 1), vec![(1, 0, STATUS_OK, vec![])]);
        release(rt);
    }

    #[test]
    fn queue_works_after_a_panic_while_its_lock_was_held() {
        let rt = runtime();
        let queue = unsafe { &*rt }.queue.clone();
        let poisoner = queue.clone();
        let poisoned = std::thread::spawn(move || {
            let _held = poisoner.events.lock();
            panic!("poison")
        })
        .join();
        assert!(poisoned.is_err());

        queue.push(1, 0, STATUS_OK, Vec::new());

        assert_eq!(events(rt, 1), vec![(1, 0, STATUS_OK, vec![])]);
        release(rt);
    }

    #[test]
    fn poll_error_is_an_error_event() -> Checked {
        let rt = runtime();
        let queue = &unsafe { &*rt }.queue;
        let failed: Result<ActivityTask, _> = Err(PollError::TonicError(
            tonic::Status::unavailable("server down"),
        ));

        queue.push_poll(5, KIND_ACTIVITY_TASK, failed);

        let [(tag, kind, status, message)] = only(events(rt, 1))?;
        assert_eq!((tag, kind, status), (5, KIND_ACTIVITY_TASK, STATUS_ERROR));
        assert!(String::from_utf8_lossy(&message).contains("server down"));
        release(rt);
        Ok(())
    }
}
