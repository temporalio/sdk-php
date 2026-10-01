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
    let result =
        catch_unwind(AssertUnwindSafe(body)).unwrap_or_else(|panic| Err(panic_message(panic)));
    unsafe { into_ffi(result, err, err_len) }
}

unsafe fn into_ffi<T>(result: Result<T, String>, err: *mut *mut u8, err_len: *mut usize) -> *mut T {
    let message = match result {
        Ok(value) => return Box::into_raw(Box::new(value)),
        Err(message) => message,
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
