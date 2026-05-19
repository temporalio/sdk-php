package main

import (
	"context"
	"time"

	"go.temporal.io/sdk/client"
	"go.temporal.io/sdk/workflow"

	gorunner "github.com/temporalio/sdk-php/parity/go-runner"
)

func SayActivity(ctx context.Context, name string) (string, error) {
	return "echoed:" + name, nil
}

func ActivityWorkflow(ctx workflow.Context, name string) (string, error) {
	ctx = workflow.WithActivityOptions(ctx, workflow.ActivityOptions{
		StartToCloseTimeout: 5 * time.Second,
	})
	var result string
	if err := workflow.ExecuteActivity(ctx, "say", name).Get(ctx, &result); err != nil {
		return "", err
	}
	return result, nil
}

func main() {
	gorunner.Run(gorunner.RunArgs{
		Slug:             "activity",
		DefaultNamespace: "parity-activity",
		DefaultTaskQueue: "TemporalTestsParityBasicActivityPhp",
		Workflows: []gorunner.Registration{
			{Fn: ActivityWorkflow, Name: "Parity_Basic_Activity"},
		},
		Activities: []gorunner.Registration{
			{Fn: SayActivity, Name: "say"},
		},
		Driver: func(ctx context.Context, c client.Client, workflowID, taskQueue string) (any, error) {
			run, err := c.ExecuteWorkflow(ctx, client.StartWorkflowOptions{
				ID:        workflowID,
				TaskQueue: taskQueue,
			}, "Parity_Basic_Activity", "world")
			if err != nil {
				return nil, err
			}
			var out string
			if err := run.Get(ctx, &out); err != nil {
				return nil, err
			}
			return out, nil
		},
		Expected: "echoed:world",
	})
}
