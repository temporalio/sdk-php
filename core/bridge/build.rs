fn main() {
    let crate_dir = std::env::var("CARGO_MANIFEST_DIR").unwrap();
    cbindgen::Builder::new()
        .with_crate(crate_dir)
        .with_config(cbindgen::Config {
            language: cbindgen::Language::C,
            no_includes: true,
            usize_is_size_t: true,
            documentation: false,
            ..Default::default()
        })
        .generate()
        .expect("Unable to generate the C header")
        .write_to_file("include/temporal_php_bridge.h");
}
