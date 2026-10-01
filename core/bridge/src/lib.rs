mod client;
mod config;
mod ffi;
mod queue;
mod replay;
mod runtime;
mod worker;

#[global_allocator]
static GLOBAL: mimalloc::MiMalloc = mimalloc::MiMalloc;
