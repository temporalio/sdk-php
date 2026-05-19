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

func SleepLongActivity(ctx context.Context) (string, error) {
	select {
	case <-time.After(5 * time.Second):
		return "unreachable", nil
	case <-ctx.Done():
		return "", ctx.Err()
	}
}

func ActivityTimeoutWorkflow(ctx workflow.Context) (string, error) {
	ctx = workflow.WithActivityOptions(ctx, workflow.ActivityOptions{
		StartToCloseTimeout: 500 * time.Millisecond,
		RetryPolicy: &temporal.RetryPolicy{
			MaximumAttempts: 1,
		},
	})
	var result string
	err := workflow.ExecuteActivity(ctx, "sleepLong").Get(ctx, &result)
	var activityErr *temporal.ActivityError
	if errors.As(err, &activityErr) {
		return "timed-out", nil
	}
	if err != nil {
		return "", err
	}
	return "unexpected:no-timeout", nil
}

func main() {
	gorunner.Run(gorunner.RunArgs{
		Slug:             "activitytimeout",
		DefaultNamespace: "parity-activity-timeout",
		DefaultTaskQueue: "TemporalTestsParityBasicActivityTimeoutPhp",
		Workflows: []gorunner.Registration{
			{Fn: ActivityTimeoutWorkflow, Name: "Parity_Basic_ActivityTimeout"},
		},
		Activities: []gorunner.Registration{
			{Fn: SleepLongActivity, Name: "sleepLong"},
		},
		Driver: func(ctx context.Context, c client.Client, workflowID, taskQueue string) (any, error) {
			run, err := c.ExecuteWorkflow(ctx, client.StartWorkflowOptions{
				ID:        workflowID,
				TaskQueue: taskQueue,
			}, "Parity_Basic_ActivityTimeout")
			if err != nil {
				return nil, err
			}
			var out string
			if err := run.Get(ctx, &out); err != nil {
				return nil, err
			}
			return out, nil
		},
		Expected: "timed-out",
	})
}
