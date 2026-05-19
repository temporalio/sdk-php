package main

import (
	"context"
	"time"

	"go.temporal.io/sdk/client"
	"go.temporal.io/sdk/workflow"

	gorunner "github.com/temporalio/sdk-php/parity/go-runner"
)

func WaitForSignalWorkflow(ctx workflow.Context) (string, error) {
	signalChan := workflow.GetSignalChannel(ctx, "release")
	signalChan.Receive(ctx, nil)
	return "signaled", nil
}

func main() {
	gorunner.Run(gorunner.RunArgs{
		Slug:             "signal",
		DefaultNamespace: "parity-signal",
		DefaultTaskQueue: "TemporalTestsParityBasicSignalPhp",
		Workflows: []gorunner.Registration{
			{Fn: WaitForSignalWorkflow, Name: "Parity_Basic_Signal"},
		},
		Driver: func(ctx context.Context, c client.Client, workflowID, taskQueue string) (any, error) {
			run, err := c.ExecuteWorkflow(ctx, client.StartWorkflowOptions{
				ID:        workflowID,
				TaskQueue: taskQueue,
			}, "Parity_Basic_Signal")
			if err != nil {
				return nil, err
			}
			time.Sleep(200 * time.Millisecond)
			if err := c.SignalWorkflow(ctx, workflowID, run.GetRunID(), "release", nil); err != nil {
				return nil, err
			}
			var out string
			if err := run.Get(ctx, &out); err != nil {
				return nil, err
			}
			return out, nil
		},
		Expected: "signaled",
	})
}
