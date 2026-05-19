// Package gorunner is the shared scenario harness used by every Go binary
// under tests/Parity/Scenarios/<Scenario>/go. It mirrors java-runner: a single
// Run / RunCapturingFirstRun call dials a client, starts a worker, runs the
// scenario driver, asserts the expected result, and halts the process so
// the fixture-runner can dump the workflow history.
package gorunner
