package main

import (
	"context"
	"time"

	"go.temporal.io/sdk/client"
	"go.temporal.io/sdk/workflow"

	gorunner "github.com/temporalio/sdk-php/parity/Runner/go-runner"
)

func SayLocalActivity(ctx context.Context, name string) (string, error) {
	return "local-echoed:" + name, nil
}

func LocalActivityWorkflow(ctx workflow.Context, name string) (string, error) {
	ctx = workflow.WithLocalActivityOptions(ctx, workflow.LocalActivityOptions{
		StartToCloseTimeout: 5 * time.Second,
	})
	var result string
	if err := workflow.ExecuteLocalActivity(ctx, SayLocalActivity, name).Get(ctx, &result); err != nil {
		return "", err
	}
	return result, nil
}

func main() {
	gorunner.Run(gorunner.RunArgs{
		Slug:             "localactivity",
		DefaultNamespace: "parity-local-activity",
		DefaultTaskQueue: "TemporalTestsParityBasicLocalActivityPhp",
		Workflows: []gorunner.Registration{
			{Fn: LocalActivityWorkflow, Name: "Parity_Basic_LocalActivity"},
		},
		Activities: []gorunner.Registration{
			{Fn: SayLocalActivity, Name: "say"},
		},
		Driver: func(ctx context.Context, c client.Client, workflowID, taskQueue string) (any, error) {
			run, err := c.ExecuteWorkflow(ctx, client.StartWorkflowOptions{
				ID:        workflowID,
				TaskQueue: taskQueue,
			}, "Parity_Basic_LocalActivity", "world")
			if err != nil {
				return nil, err
			}
			var out string
			if err := run.Get(ctx, &out); err != nil {
				return nil, err
			}
			return out, nil
		},
		Expected: "local-echoed:world",
	})
}
