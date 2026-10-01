use crate::ffi::{
    STATUS_ERROR, STATUS_OK, STATUS_SHUTDOWN, TpbEvent, guard, into_raw_bytes, panic_message,
};
use crate::runtime::TpbRuntime;
use futures_util::FutureExt;
use prost::Message;
use std::{
    collections::VecDeque,
    future::Future,
    os::fd::{AsRawFd, FromRawFd, OwnedFd, RawFd},
    panic::AssertUnwindSafe,
    sync::{
        Arc, Condvar, Mutex,
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
    read_fd: OwnedFd,
    write_fd: OwnedFd,
    fd_watched: AtomicBool,
}

impl Queue {
    pub fn new(handle: Handle) -> Result<Self, String> {
        let mut fds: [RawFd; 2] = [0; 2];
        if unsafe { libc::pipe(fds.as_mut_ptr()) } != 0 {
            return Err(std::io::Error::last_os_error().to_string());
        }
        let [read_fd, write_fd] = fds.map(|fd| unsafe { OwnedFd::from_raw_fd(fd) });
        for fd in [&read_fd, &write_fd] {
            let fd = fd.as_raw_fd();
            unsafe {
                libc::fcntl(
                    fd,
                    libc::F_SETFL,
                    libc::fcntl(fd, libc::F_GETFL) | libc::O_NONBLOCK,
                );
                libc::fcntl(fd, libc::F_SETFD, libc::FD_CLOEXEC);
            }
        }
        Ok(Self {
            handle,
            events: Mutex::new(VecDeque::new()),
            ready: Condvar::new(),
            read_fd,
            write_fd,
            fd_watched: AtomicBool::new(false),
        })
    }

    pub fn push(&self, tag: u64, kind: i32, status: i32, data: Vec<u8>) {
        self.events.lock().unwrap().push_back(Event {
            tag,
            kind,
            status,
            data: data.into_boxed_slice(),
        });
        self.ready.notify_one();
        if self.fd_watched.load(Ordering::Relaxed) {
            unsafe { libc::write(self.write_fd.as_raw_fd(), [1u8].as_ptr().cast(), 1) };
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

    fn take(&self, timeout_ms: i32, out: &mut [TpbEvent]) -> usize {
        let mut events = self.events.lock().unwrap();
        if events.is_empty() && timeout_ms != 0 {
            events = if timeout_ms < 0 {
                self.ready.wait_while(events, |e| e.is_empty()).unwrap()
            } else {
                let timeout = Duration::from_millis(timeout_ms as u64);
                self.ready
                    .wait_timeout_while(events, timeout, |e| e.is_empty())
                    .unwrap()
                    .0
            };
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
    guard(
        |_| -1,
        || {
            let queue = &unsafe { &*rt }.queue;
            queue.fd_watched.store(true, Ordering::Relaxed);
            queue.read_fd.as_raw_fd()
        },
    )
}

#[unsafe(no_mangle)]
pub unsafe extern "C" fn tpb_next_events(
    rt: *mut TpbRuntime,
    timeout_ms: i32,
    out: *mut TpbEvent,
    max: usize,
) -> usize {
    guard(
        |_| 0,
        || {
            let out = unsafe { std::slice::from_raw_parts_mut(out, max) };
            unsafe { &*rt }.queue.take(timeout_ms, out)
        },
    )
}
