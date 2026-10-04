use crate::ffi::{
    STATUS_ERROR, STATUS_OK, STATUS_SHUTDOWN, TpbEvent, guard, into_raw_bytes, panic_message,
};
use crate::runtime::TpbRuntime;
use futures_util::FutureExt;
use prost::Message;
use std::{
    collections::VecDeque,
    future::Future,
    os::fd::{AsRawFd, OwnedFd},
    panic::AssertUnwindSafe,
    sync::{
        Arc, Condvar, Mutex,
        atomic::{AtomicBool, Ordering},
    },
    time::Duration,
};
use temporalio_sdk_core::PollError;
use tokio::runtime::Handle;

#[derive(Debug)]
struct Event {
    tag: u64,
    kind: i32,
    status: i32,
    data: Box<[u8]>,
}

#[derive(Debug)]
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
        let (read_fd, write_fd) = std::io::pipe().map_err(|e| e.to_string())?;
        let (read_fd, write_fd) = (OwnedFd::from(read_fd), OwnedFd::from(write_fd));
        for fd in [&read_fd, &write_fd] {
            let fd = fd.as_raw_fd();
            unsafe {
                libc::fcntl(
                    fd,
                    libc::F_SETFL,
                    libc::fcntl(fd, libc::F_GETFL) | libc::O_NONBLOCK,
                )
            };
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

    fn take(&self, timeout_ms: u32, out: &mut [TpbEvent]) -> usize {
        let mut events = self.events.lock().unwrap();
        if events.is_empty() && timeout_ms > 0 {
            let timeout = Duration::from_millis(timeout_ms.into());
            events = self
                .ready
                .wait_timeout_while(events, timeout, |e| e.is_empty())
                .unwrap()
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
    timeout_ms: u32,
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

#[cfg(test)]
mod tests {
    use super::*;
    use crate::ffi::free;
    use crate::testing::{events, runtime};

    fn read_bytes(fd: libc::c_int) -> isize {
        let mut buf = [0u8; 8];
        unsafe { libc::read(fd, buf.as_mut_ptr().cast(), buf.len()) }
    }

    #[test]
    fn event_fd_gets_one_byte_per_event_after_it_is_watched() {
        let rt = runtime();
        let queue = &unsafe { &*rt }.queue;
        queue.push(1, 0, STATUS_OK, Vec::new());
        assert_eq!(read_bytes(queue.read_fd.as_raw_fd()), -1);

        let fd = unsafe { tpb_event_fd(rt) };
        assert_eq!(fd, queue.read_fd.as_raw_fd());
        queue.push(2, 0, STATUS_OK, Vec::new());
        queue.push(3, 0, STATUS_OK, Vec::new());
        assert_eq!(read_bytes(fd), 2);
        assert_eq!(read_bytes(fd), -1);

        assert_eq!(events(rt, 3).len(), 3);
        unsafe { free(rt) };
    }
}
