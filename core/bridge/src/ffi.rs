use std::{
    any::Any,
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

pub unsafe fn slice<'a>(data: *const libc::c_char, len: usize) -> &'a [u8] {
    if data.is_null() || len == 0 {
        return &[];
    }
    unsafe { std::slice::from_raw_parts(data.cast(), len) }
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

pub unsafe fn construct<T>(
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

pub unsafe fn free<T>(value: *mut T) {
    guard(
        |_| (),
        || {
            if !value.is_null() {
                drop(unsafe { Box::from_raw(value) });
            }
        },
    )
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
        assert!(unsafe { construct(&mut err, &mut err_len, || failed) }.is_null());
        assert_eq!(unsafe { slice(err.cast(), err_len) }, b"no worker");
        unsafe { tpb_bytes_free(err, err_len) };

        let panicked = unsafe {
            construct(&mut err, &mut err_len, || -> Result<u8, String> {
                panic!("boom")
            })
        };
        assert!(panicked.is_null());
        assert_eq!(
            unsafe { slice(err.cast(), err_len) },
            b"Panic in temporal-php-bridge: boom"
        );
        unsafe { tpb_bytes_free(err, err_len) };

        let unreported: Result<u8, String> = Err("lost".into());
        let null = std::ptr::null_mut();
        assert!(unsafe { construct(null, std::ptr::null_mut(), || unreported) }.is_null());
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
        unsafe {
            tpb_bytes_free(std::ptr::null_mut(), 0);
            free(std::ptr::null_mut::<u8>());
        }
    }
}
