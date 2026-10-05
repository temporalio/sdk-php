use std::{
    any::Any,
    io,
    os::fd::AsRawFd,
    panic::{AssertUnwindSafe, catch_unwind},
};

pub const KIND_WORKFLOW_ACTIVATION: i32 = 1;
pub const KIND_ACTIVITY_TASK: i32 = 2;
pub const KIND_WORKFLOW_COMPLETED: i32 = 3;
pub const KIND_ACTIVITY_COMPLETED: i32 = 4;
pub const KIND_SHUTDOWN_FINALIZED: i32 = 5;
pub const KIND_RPC_RESULT: i32 = 6;
pub const KIND_LOG: i32 = 7;

pub const STATUS_OK: i32 = 0;
pub const STATUS_ERROR: i32 = 1;
pub const STATUS_SHUTDOWN: i32 = 2;

pub const CALL_OK: i32 = 0;
pub const CALL_FAILED: i32 = 1;

#[repr(C)]
pub struct TpbEvent {
    pub tag: u64,
    pub kind: i32,
    pub status: i32,
    pub data: *mut u8,
    pub len: usize,
}

pub fn into_raw_bytes(bytes: Box<[u8]>) -> (*mut u8, usize) {
    if bytes.is_empty() {
        return (std::ptr::null_mut(), 0);
    }
    let len = bytes.len();
    (Box::into_raw(bytes).cast(), len)
}

pub fn object<'a, T>(ptr: *mut T) -> Option<&'a T> {
    unsafe { ptr.as_ref() }
}

pub fn required<'a, T>(ptr: *mut T, name: &str) -> Result<&'a T, String> {
    object(ptr).ok_or_else(|| format!("The {name} pointer is null"))
}

pub fn bytes<'a>(data: *const libc::c_char, len: usize) -> &'a [u8] {
    if data.is_null() || len == 0 {
        return &[];
    }
    unsafe { std::slice::from_raw_parts(data.cast(), len) }
}

pub unsafe fn slice<'a>(data: *const libc::c_char, len: usize) -> &'a [u8] {
    bytes(data, len)
}

pub fn events_out<'a>(out: *mut TpbEvent, max: usize) -> &'a mut [TpbEvent] {
    if out.is_null() || max == 0 {
        return &mut [];
    }
    unsafe { std::slice::from_raw_parts_mut(out, max) }
}

pub fn set_nonblocking(fd: &impl AsRawFd) -> io::Result<()> {
    let fd = fd.as_raw_fd();
    let flags = unsafe { libc::fcntl(fd, libc::F_GETFL) };
    if flags < 0 || unsafe { libc::fcntl(fd, libc::F_SETFL, flags | libc::O_NONBLOCK) } < 0 {
        return Err(io::Error::last_os_error());
    }
    Ok(())
}

pub fn panic_message(panic: Box<dyn Any + Send>) -> String {
    let message = panic
        .downcast_ref::<&str>()
        .copied()
        .or_else(|| panic.downcast_ref::<String>().map(String::as_str))
        .unwrap_or("unknown panic");
    format!("Panic in temporal-php-bridge: {message}")
}

pub fn guard<T>(fallback: impl FnOnce(String) -> T, body: impl FnOnce() -> T) -> T {
    catch_unwind(AssertUnwindSafe(body)).unwrap_or_else(|panic| fallback(panic_message(panic)))
}

pub fn call<T, R: Copy>(ptr: *mut T, failed: R, body: impl FnOnce(&T) -> R) -> R {
    guard(|_| failed, || object(ptr).map_or(failed, body))
}

pub fn construct<T>(
    err: *mut *mut u8,
    err_len: *mut usize,
    body: impl FnOnce() -> Result<T, String>,
) -> *mut T {
    let message = match catch_unwind(AssertUnwindSafe(body)) {
        Ok(Ok(value)) => return Box::into_raw(Box::new(value)),
        Ok(Err(message)) => message,
        Err(panic) => panic_message(panic),
    };
    if let (Some(err), Some(err_len)) = unsafe { (err.as_mut(), err_len.as_mut()) } {
        (*err, *err_len) = into_raw_bytes(message.into_bytes().into_boxed_slice());
    }
    std::ptr::null_mut()
}

pub fn release<T>(value: *mut T) {
    guard(
        |_| (),
        || {
            if !value.is_null() {
                drop(unsafe { Box::from_raw(value) });
            }
        },
    )
}

pub unsafe fn free<T>(value: *mut T) {
    release(value)
}

#[unsafe(no_mangle)]
pub unsafe extern "C" fn tpb_bytes_free(data: *mut u8, len: usize) {
    guard(
        |_| (),
        || {
            if !data.is_null() && len > 0 {
                drop(unsafe { Box::from_raw(std::ptr::slice_from_raw_parts_mut(data, len)) });
            }
        },
    )
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn construct_returns_errors_and_panics_through_the_error_pointers() {
        let (mut err, mut err_len) = (std::ptr::null_mut(), 0);
        let failed: Result<u8, String> = Err("no worker".into());
        assert!(construct(&mut err, &mut err_len, || failed).is_null());
        assert_eq!(bytes(err.cast(), err_len), b"no worker");
        unsafe { tpb_bytes_free(err, err_len) };

        let panicked = construct(&mut err, &mut err_len, || -> Result<u8, String> {
            panic!("boom")
        });
        assert!(panicked.is_null());
        assert_eq!(
            bytes(err.cast(), err_len),
            b"Panic in temporal-php-bridge: boom"
        );
        unsafe { tpb_bytes_free(err, err_len) };

        let unreported: Result<u8, String> = Err("lost".into());
        let null = std::ptr::null_mut();
        assert!(construct(null, std::ptr::null_mut(), || unreported).is_null());
    }

    struct ClosedFd;

    impl AsRawFd for ClosedFd {
        fn as_raw_fd(&self) -> std::os::fd::RawFd {
            -1
        }
    }

    #[test]
    fn set_nonblocking_reports_an_invalid_fd() {
        let error = set_nonblocking(&ClosedFd).err().map(|e| e.raw_os_error());

        assert_eq!(error, Some(Some(libc::EBADF)));
    }

    #[test]
    fn panic_message_reads_str_and_string_payloads() {
        let message = |payload: Box<dyn Any + Send>| panic_message(payload);
        assert_eq!(message(Box::new("a")), "Panic in temporal-php-bridge: a");
        assert_eq!(
            message(Box::new(String::from("b"))),
            "Panic in temporal-php-bridge: b"
        );
        assert_eq!(
            message(Box::new(42)),
            "Panic in temporal-php-bridge: unknown panic"
        );
    }

    #[test]
    fn freeing_null_does_nothing() {
        unsafe { tpb_bytes_free(std::ptr::null_mut(), 0) };
        release(std::ptr::null_mut::<u8>());
    }
}
