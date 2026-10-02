use crate::ffi::{TpbEvent, slice, tpb_bytes_free};
use crate::queue::tpb_next_events;
use crate::runtime::{TpbRuntime, tpb_runtime_new};

pub type Event = (u64, i32, i32, Vec<u8>);

pub fn runtime() -> *mut TpbRuntime {
    let config = br#"{"threads":1,"log":null}"#;
    let rt = unsafe {
        tpb_runtime_new(
            config.as_ptr().cast(),
            config.len(),
            std::ptr::null_mut(),
            std::ptr::null_mut(),
        )
    };
    assert!(!rt.is_null());
    rt
}

fn empty_events<const N: usize>() -> [TpbEvent; N] {
    std::array::from_fn(|_| TpbEvent {
        tag: 0,
        kind: 0,
        status: 0,
        data: std::ptr::null_mut(),
        len: 0,
    })
}

pub fn pending_events(rt: *mut TpbRuntime) -> usize {
    let mut buf: [TpbEvent; 1] = empty_events();
    unsafe { tpb_next_events(rt, 300, buf.as_mut_ptr(), buf.len()) }
}

pub fn events(rt: *mut TpbRuntime, count: usize) -> Vec<Event> {
    let mut events = Vec::new();
    while events.len() < count {
        let mut buf: [TpbEvent; 8] = empty_events();
        let taken = unsafe { tpb_next_events(rt, 5_000, buf.as_mut_ptr(), buf.len()) };
        assert!(taken > 0, "no event in 5 seconds, received {events:?}");
        for e in &buf[..taken] {
            events.push((
                e.tag,
                e.kind,
                e.status,
                unsafe { slice(e.data.cast(), e.len) }.to_vec(),
            ));
            unsafe { tpb_bytes_free(e.data, e.len) };
        }
    }
    events.sort();
    events
}
