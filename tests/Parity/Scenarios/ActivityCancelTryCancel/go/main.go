package main

import (
	"context"
	"time"

	"go.temporal.io/sdk/activity"
	"go.temporal.io/sdk/client"
	"go.temporal.io/sdk/temporal"
	"go.temporal.io/sdk/workflow"

	gorunner "github.com/temporalio/sdk-php/parity/Runner/go-runner"
)

func CancellableActivity(ctx context.Context) error {
	for i := 0; i < 50; i++ {
		select {
		case <-time.After(100 * time.Millisecond):
			activity.RecordHeartbeat(ctx, i)
		case <-ctx.Done():
			return nil
		}
	}
	return nil
}

func ActivityCancelTryCancelWorkflow(ctx workflow.Context) (string, error) {
	actCtx, actCancel := workflow.WithCancel(workflow.WithActivityOptions(ctx, workflow.ActivityOptions{
		ScheduleToCloseTimeout: 1 * time.Minute,
		HeartbeatTimeout:       5 * time.Second,
		RetryPolicy:            &temporal.RetryPolicy{MaximumAttempts: 1},
		WaitForCancellation:    false,
	}))
	defer actCancel()

	future := workflow.ExecuteActivity(actCtx, "cancellable")

	if err := workflow.Sleep(ctx, 1*time.Second); err != nil {
		return "", err
	}

	actCancel()

	err := future.Get(ctx, nil)
	if temporal.IsCanceledError(err) {
		return "cancelled", nil
	}
	if err == nil {
		return "unexpected:not-cancelled", nil
	}
	return "", err
}

func main() {
	gorunner.Run(gorunner.RunArgs{
		Slug:             "harness-activity-cancel-try-cancel",
		DefaultNamespace: "parity-activity-cancel-try-cancel",
		DefaultTaskQueue: "TemporalTestsParityHarnessActivityCancelTryCancelPhp",
		Workflows: []gorunner.Registration{
			{Fn: ActivityCancelTryCancelWorkflow, Name: "Parity_Harness_ActivityCancelTryCancel"},
		},
		Activities: []gorunner.Registration{
			{Fn: CancellableActivity, Name: "cancellable"},
		},
		Timeout: 30 * time.Second,
		Driver: func(ctx context.Context, c client.Client, workflowID, taskQueue string) (any, error) {
			run, err := c.ExecuteWorkflow(ctx, client.StartWorkflowOptions{
				ID:        workflowID,
				TaskQueue: taskQueue,
			}, "Parity_Harness_ActivityCancelTryCancel")
			if err != nil {
				return nil, err
			}
			var out string
			if err := run.Get(ctx, &out); err != nil {
				return nil, err
			}
			return out, nil
		},
		Expected: "cancelled",
	})
}
