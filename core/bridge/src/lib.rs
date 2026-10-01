mod client;
mod config;
mod ffi;
mod queue;
mod replay;
mod runtime;
#[cfg(test)]
mod testing;
mod worker;

#[global_allocator]
static GLOBAL: mimalloc::MiMalloc = mimalloc::MiMalloc;
