package main

import (
	"context"
	"time"

	"go.temporal.io/sdk/client"
	"go.temporal.io/sdk/workflow"

	gorunner "github.com/temporalio/sdk-php/parity/go-runner"
)

func TimerWorkflow(ctx workflow.Context) (string, error) {
	if err := workflow.Sleep(ctx, time.Second); err != nil {
		return "", err
	}
	return "done", nil
}

func main() {
	gorunner.Run(gorunner.RunArgs{
		Slug:             "timer",
		DefaultNamespace: "parity-timer",
		DefaultTaskQueue: `Temporal\Tests\Parity\Basic\Timer\Php`,
		Workflows: []gorunner.Registration{
			{Fn: TimerWorkflow, Name: "Parity_Basic_Timer"},
		},
		Driver: func(ctx context.Context, c client.Client, workflowID, taskQueue string) (any, error) {
			run, err := c.ExecuteWorkflow(ctx, client.StartWorkflowOptions{
				ID:        workflowID,
				TaskQueue: taskQueue,
			}, "Parity_Basic_Timer")
			if err != nil {
				return nil, err
			}
			var out string
			if err := run.Get(ctx, &out); err != nil {
				return nil, err
			}
			return out, nil
		},
		Expected: "done",
	})
}
