package main

import (
	"context"
	"errors"
	"time"

	"go.temporal.io/sdk/client"
	"go.temporal.io/sdk/temporal"
	"go.temporal.io/sdk/workflow"

	gorunner "github.com/temporalio/sdk-php/parity/Runner/go-runner"
)

func FailActivity(ctx context.Context) (string, error) {
	return "", errors.New("always-fails")
}

func ActivityRetryWorkflow(ctx workflow.Context) (string, error) {
	ctx = workflow.WithActivityOptions(ctx, workflow.ActivityOptions{
		StartToCloseTimeout: 2 * time.Second,
		RetryPolicy: &temporal.RetryPolicy{
			InitialInterval:    10 * time.Millisecond,
			BackoffCoefficient: 1.0,
			MaximumAttempts:    3,
		},
	})
	var result string
	err := workflow.ExecuteActivity(ctx, "fail").Get(ctx, &result)
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
		Slug:             "activityretry",
		DefaultNamespace: "parity-activity-retry",
		DefaultTaskQueue: "TemporalTestsParityBasicActivityRetryPhp",
		Workflows: []gorunner.Registration{
			{Fn: ActivityRetryWorkflow, Name: "Parity_Basic_ActivityRetry"},
		},
		Activities: []gorunner.Registration{
			{Fn: FailActivity, Name: "fail"},
		},
		Driver: func(ctx context.Context, c client.Client, workflowID, taskQueue string) (any, error) {
			run, err := c.ExecuteWorkflow(ctx, client.StartWorkflowOptions{
				ID:        workflowID,
				TaskQueue: taskQueue,
			}, "Parity_Basic_ActivityRetry")
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
