package main

import (
	"context"
	"time"

	"go.temporal.io/sdk/client"
	"go.temporal.io/sdk/workflow"

	gorunner "github.com/temporalio/sdk-php/parity/Runner/go-runner"
)

func AwaitWithTimeoutWorkflow(ctx workflow.Context) (string, error) {
	released := false
	workflow.Go(ctx, func(gctx workflow.Context) {
		workflow.GetSignalChannel(gctx, "release").Receive(gctx, nil)
		released = true
	})
	ok, err := workflow.AwaitWithTimeout(ctx, 5*time.Second, func() bool { return released })
	if err != nil {
		return "", err
	}
	if ok {
		return "got", nil
	}
	return "timed-out", nil
}

func main() {
	gorunner.Run(gorunner.RunArgs{
		Slug:             "basic-await-with-timeout",
		DefaultNamespace: "parity-await-with-timeout",
		DefaultTaskQueue: "TemporalTestsParityBasicAwaitWithTimeoutPhp",
		Workflows: []gorunner.Registration{
			{Fn: AwaitWithTimeoutWorkflow, Name: "Parity_Basic_AwaitWithTimeout"},
		},
		Driver: func(ctx context.Context, c client.Client, workflowID, taskQueue string) (any, error) {
			run, err := c.ExecuteWorkflow(ctx, client.StartWorkflowOptions{
				ID:        workflowID,
				TaskQueue: taskQueue,
			}, "Parity_Basic_AwaitWithTimeout")
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
		Expected: "got",
	})
}
