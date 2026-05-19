package main

import (
	"context"
	"time"

	"go.temporal.io/sdk/client"
	"go.temporal.io/sdk/workflow"

	gorunner "github.com/temporalio/sdk-php/parity/go-runner"
)

func MultipleTimersWorkflow(ctx workflow.Context) (string, error) {
	if err := workflow.Sleep(ctx, 50*time.Millisecond); err != nil {
		return "", err
	}
	if err := workflow.Sleep(ctx, 50*time.Millisecond); err != nil {
		return "", err
	}
	if err := workflow.Sleep(ctx, 50*time.Millisecond); err != nil {
		return "", err
	}
	return "tick-tick-tick", nil
}

func main() {
	gorunner.Run(gorunner.RunArgs{
		Slug:             "basic-multiple-timers",
		DefaultNamespace: "parity-multiple-timers",
		DefaultTaskQueue: "TemporalTestsParityBasicMultipleTimersPhp",
		Workflows: []gorunner.Registration{
			{Fn: MultipleTimersWorkflow, Name: "Parity_Basic_MultipleTimers"},
		},
		Driver: func(ctx context.Context, c client.Client, workflowID, taskQueue string) (any, error) {
			run, err := c.ExecuteWorkflow(ctx, client.StartWorkflowOptions{
				ID:        workflowID,
				TaskQueue: taskQueue,
			}, "Parity_Basic_MultipleTimers")
			if err != nil {
				return nil, err
			}
			var out string
			if err := run.Get(ctx, &out); err != nil {
				return nil, err
			}
			return out, nil
		},
		Expected: "tick-tick-tick",
	})
}
