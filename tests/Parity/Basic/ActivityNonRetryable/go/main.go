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

func FatalActivity(ctx context.Context) (string, error) {
	return "", temporal.NewNonRetryableApplicationError("do-not-retry", "FatalAppError", nil)
}

func ActivityNonRetryableWorkflow(ctx workflow.Context) (string, error) {
	ctx = workflow.WithActivityOptions(ctx, workflow.ActivityOptions{
		StartToCloseTimeout: 2 * time.Second,
		RetryPolicy: &temporal.RetryPolicy{
			InitialInterval:    10 * time.Millisecond,
			BackoffCoefficient: 1.0,
			MaximumAttempts:    5,
		},
	})
	var result string
	err := workflow.ExecuteActivity(ctx, "fatal").Get(ctx, &result)
	var activityErr *temporal.ActivityError
	if errors.As(err, &activityErr) {
		return "non-retryable", nil
	}
	if err != nil {
		return "", err
	}
	return "unexpected:no-failure", nil
}

func main() {
	gorunner.Run(gorunner.RunArgs{
		Slug:             "basic-activity-non-retryable",
		DefaultNamespace: "parity-activity-non-retryable",
		DefaultTaskQueue: "TemporalTestsParityBasicActivityNonRetryablePhp",
		Workflows: []gorunner.Registration{
			{Fn: ActivityNonRetryableWorkflow, Name: "Parity_Basic_ActivityNonRetryable"},
		},
		Activities: []gorunner.Registration{
			{Fn: FatalActivity, Name: "fatal"},
		},
		Driver: func(ctx context.Context, c client.Client, workflowID, taskQueue string) (any, error) {
			run, err := c.ExecuteWorkflow(ctx, client.StartWorkflowOptions{
				ID:        workflowID,
				TaskQueue: taskQueue,
			}, "Parity_Basic_ActivityNonRetryable")
			if err != nil {
				return nil, err
			}
			var out string
			if err := run.Get(ctx, &out); err != nil {
				return nil, err
			}
			return out, nil
		},
		Expected: "non-retryable",
	})
}
