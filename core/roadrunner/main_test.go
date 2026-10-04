package main

import (
	"net"
	"os"
	"os/signal"
	"path/filepath"
	"runtime"
	"strings"
	"syscall"
	"testing"
	"time"
)

func config(t *testing.T, yaml string) string {
	t.Helper()
	t.Chdir(".")
	dir := t.TempDir()
	if err := os.WriteFile(filepath.Join(dir, ".rr.yaml"), []byte("version: \"3\"\n"+yaml), 0o600); err != nil {
		t.Fatal(err)
	}
	return dir
}

func TestRunRejectsUnknownFlagAndMissingDirectory(t *testing.T) {
	if err := run([]string{"-unknown"}); err == nil {
		t.Fatal("unknown flag accepted")
	}
	if err := run([]string{"-w", filepath.Join(t.TempDir(), "missing")}); err == nil {
		t.Fatal("missing working directory accepted")
	}
}

func TestRunFailsWithoutConfig(t *testing.T) {
	dir := config(t, "")
	if err := run([]string{"-w", dir, "-c", "missing.yaml"}); err == nil {
		t.Fatal("missing config accepted")
	}
}

func TestRunFailsWhenTheRpcAddressIsTaken(t *testing.T) {
	taken, err := net.Listen("tcp", "127.0.0.1:0")
	if err != nil {
		t.Fatal(err)
	}
	defer taken.Close()
	dir := config(t, "rpc:\n  listen: tcp://"+taken.Addr().String()+"\n")

	err = run([]string{"-w", dir})

	if err == nil || !strings.Contains(err.Error(), "serve error from the plugin") {
		t.Fatalf("rpc serve error expected, got %v", err)
	}
}

func TestRunReturnsAPluginErrorAfterServe(t *testing.T) {
	defer runtime.GOMAXPROCS(runtime.GOMAXPROCS(1))
	dir := config(t, "service:\n  missing:\n    command: "+filepath.Join(t.TempDir(), "missing")+"\n")

	err := run([]string{"-w", dir})

	if err == nil || !strings.HasPrefix(err.Error(), "plugin *service.Plugin: ") {
		t.Fatalf("service plugin error expected, got %v", err)
	}
}

func TestServeStopsOnSignal(t *testing.T) {
	dir := config(t, "rpc:\n  listen: tcp://127.0.0.1:0\n")
	ignored := make(chan os.Signal, 1)
	signal.Notify(ignored, syscall.SIGINT)
	defer signal.Stop(ignored)
	done := make(chan error)
	go func() { done <- run([]string{"serve", "-w", dir, "-o", "rpc.listen=tcp://127.0.0.1:0"}) }()

	for {
		select {
		case err := <-done:
			if err != nil {
				t.Fatal(err)
			}
			return
		case <-time.After(100 * time.Millisecond):
			if err := syscall.Kill(os.Getpid(), syscall.SIGINT); err != nil {
				t.Fatal(err)
			}
		}
	}
}
