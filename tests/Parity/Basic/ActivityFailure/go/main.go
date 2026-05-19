package main

import (
	"context"
	"errors"
	"time"

	"go.temporal.io/sdk/client"
	"go.temporal.io/sdk/temporal"
	"go.temporal.io/sdk/workflow"

	gorunner "github.com/temporalio/sdk-php/parity/go-runner"
)

func BoomActivity(ctx context.Context) (string, error) {
	return "", errors.New("boom")
}

func ActivityFailureWorkflow(ctx workflow.Context) (string, error) {
	ctx = workflow.WithActivityOptions(ctx, workflow.ActivityOptions{
		StartToCloseTimeout: 2 * time.Second,
		RetryPolicy: &temporal.RetryPolicy{
			InitialInterval:    10 * time.Millisecond,
			BackoffCoefficient: 1.0,
			MaximumAttempts:    1,
		},
	})
	var result string
	err := workflow.ExecuteActivity(ctx, "boom").Get(ctx, &result)
	var activityErr *temporal.ActivityError
	if errors.As(err, &activityErr) {
		return "caught", nil
	}
	if err != nil {
		return "", err
	}
	return "unexpected:no-failure", nil
}

func main() {
	gorunner.Run(gorunner.RunArgs{
		Slug:             "basic-activity-failure",
		DefaultNamespace: "parity-activity-failure",
		DefaultTaskQueue: "TemporalTestsParityBasicActivityFailurePhp",
		Workflows: []gorunner.Registration{
			{Fn: ActivityFailureWorkflow, Name: "Parity_Basic_ActivityFailure"},
		},
		Activities: []gorunner.Registration{
			{Fn: BoomActivity, Name: "boom"},
		},
		Driver: func(ctx context.Context, c client.Client, workflowID, taskQueue string) (any, error) {
			run, err := c.ExecuteWorkflow(ctx, client.StartWorkflowOptions{
				ID:        workflowID,
				TaskQueue: taskQueue,
			}, "Parity_Basic_ActivityFailure")
			if err != nil {
				return nil, err
			}
			var out string
			if err := run.Get(ctx, &out); err != nil {
				return nil, err
			}
			return out, nil
		},
		Expected: "caught",
	})
}
