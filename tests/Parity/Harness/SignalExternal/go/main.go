package main

import (
	"context"

	"go.temporal.io/sdk/client"
	"go.temporal.io/sdk/workflow"

	gorunner "github.com/temporalio/sdk-php/parity/go-runner"
)

func SignalExternalWorkflow(ctx workflow.Context) (string, error) {
	var payload string
	workflow.GetSignalChannel(ctx, "external_signal").Receive(ctx, &payload)
	return payload, nil
}

func main() {
	gorunner.Run(gorunner.RunArgs{
		Slug:             "harness-signal-external",
		DefaultNamespace: "parity-signal-external",
		DefaultTaskQueue: "TemporalTestsParityHarnessSignalExternalPhp",
		Workflows: []gorunner.Registration{
			{Fn: SignalExternalWorkflow, Name: "Parity_Harness_SignalExternal"},
		},
		Driver: func(ctx context.Context, c client.Client, workflowID, taskQueue string) (any, error) {
			run, err := c.ExecuteWorkflow(ctx, client.StartWorkflowOptions{
				ID:        workflowID,
				TaskQueue: taskQueue,
			}, "Parity_Harness_SignalExternal")
			if err != nil {
				return nil, err
			}
			if err := c.SignalWorkflow(ctx, workflowID, run.GetRunID(), "external_signal", "Signaled!"); err != nil {
				return nil, err
			}
			var out string
			if err := run.Get(ctx, &out); err != nil {
				return nil, err
			}
			return out, nil
		},
		Expected: "Signaled!",
	})
}
