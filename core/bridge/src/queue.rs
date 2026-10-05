use crate::ffi::{
    STATUS_ERROR, STATUS_OK, STATUS_SHUTDOWN, TpbEvent, call, events_out, into_raw_bytes,
    panic_message, set_nonblocking,
};
use crate::runtime::TpbRuntime;
use futures_util::FutureExt;
use prost::Message;
use std::{
    collections::VecDeque,
    future::Future,
    io::{PipeReader, PipeWriter, Write},
    os::fd::AsRawFd,
    panic::AssertUnwindSafe,
    sync::{
        Arc, Condvar, Mutex, MutexGuard, PoisonError,
        atomic::{AtomicBool, Ordering},
    },
    time::Duration,
};
use temporalio_sdk_core::PollError;
use tokio::runtime::Handle;

struct Event {
    tag: u64,
    kind: i32,
    status: i32,
    data: Box<[u8]>,
}

pub struct Queue {
    pub handle: Handle,
    events: Mutex<VecDeque<Event>>,
    ready: Condvar,
    read_fd: PipeReader,
    write_fd: PipeWriter,
    fd_watched: AtomicBool,
}

impl Queue {
    pub fn new(handle: Handle) -> Result<Self, String> {
        let (read_fd, write_fd) = std::io::pipe().map_err(|e| e.to_string())?;
        set_nonblocking(&read_fd).map_err(|e| e.to_string())?;
        set_nonblocking(&write_fd).map_err(|e| e.to_string())?;
        Ok(Self {
            handle,
            events: Mutex::new(VecDeque::new()),
            ready: Condvar::new(),
            read_fd,
            write_fd,
            fd_watched: AtomicBool::new(false),
        })
    }

    fn events(&self) -> MutexGuard<'_, VecDeque<Event>> {
        self.events.lock().unwrap_or_else(PoisonError::into_inner)
    }

    pub fn push(&self, tag: u64, kind: i32, status: i32, data: Vec<u8>) {
        let mut events = self.events();
        let was_empty = events.is_empty();
        events.push_back(Event {
            tag,
            kind,
            status,
            data: data.into_boxed_slice(),
        });
        drop(events);
        self.ready.notify_one();
        if was_empty && self.fd_watched.load(Ordering::Relaxed) {
            let _ = (&self.write_fd).write_all(&[1]);
        }
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
        if events.is_empty() && timeout_ms > 0 {
            let timeout = Duration::from_millis(timeout_ms.into());
            events = self
                .ready
                .wait_timeout_while(events, timeout, |e| e.is_empty())
                .unwrap_or_else(PoisonError::into_inner)
                .0;
        }
        let count = events.len().min(out.len());
        for (slot, event) in out.iter_mut().zip(events.drain(..count)) {
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
pub unsafe extern "C" fn tpb_event_fd(rt: *mut TpbRuntime) -> libc::c_int {
    call(rt, -1, |rt| {
        rt.queue.fd_watched.store(true, Ordering::Relaxed);
        rt.queue.read_fd.as_raw_fd()
    })
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
    use std::io::{ErrorKind, Read};
    use temporalio_common::protos::coresdk::activity_task::ActivityTask;

    fn read_bytes(queue: &Queue) -> Result<usize, ErrorKind> {
        let mut buf = [0u8; 8];
        (&queue.read_fd).read(&mut buf).map_err(|e| e.kind())
    }

    #[test]
    fn event_fd_gets_one_byte_when_the_watched_queue_stops_being_empty() {
        let rt = runtime();
        let queue = &unsafe { &*rt }.queue;
        queue.push(1, 0, STATUS_OK, Vec::new());
        assert_eq!(read_bytes(queue), Err(ErrorKind::WouldBlock));
        assert_eq!(events(rt, 1).len(), 1);

        let fd = unsafe { tpb_event_fd(rt) };
        assert_eq!(fd, queue.read_fd.as_raw_fd());
        for tag in 2..=301 {
            queue.push(tag, 0, STATUS_OK, Vec::new());
        }
        assert_eq!(read_bytes(queue), Ok(1));
        assert_eq!(read_bytes(queue), Err(ErrorKind::WouldBlock));
        assert_eq!(events(rt, 300).len(), 300);

        queue.push(302, 0, STATUS_OK, Vec::new());
        assert_eq!(read_bytes(queue), Ok(1));
        assert_eq!(events(rt, 1).len(), 1);
        release(rt);
    }

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
    fn null_runtime_has_no_event_fd_and_no_events() {
        assert_eq!(unsafe { tpb_event_fd(std::ptr::null_mut()) }, -1);
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
