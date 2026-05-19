package main

import (
	"context"
	"time"

	"go.temporal.io/sdk/client"
	"go.temporal.io/sdk/workflow"

	gorunner "github.com/temporalio/sdk-php/parity/go-runner"
)

func EchoActivity(ctx context.Context) (string, error) {
	return "echo", nil
}

func ActivityBasicNoWorkflowTimeoutWorkflow(ctx workflow.Context) (string, error) {
	first := workflow.WithActivityOptions(ctx, workflow.ActivityOptions{
		ScheduleToCloseTimeout: 1 * time.Minute,
	})
	if err := workflow.ExecuteActivity(first, "echo").Get(first, nil); err != nil {
		return "", err
	}

	second := workflow.WithActivityOptions(ctx, workflow.ActivityOptions{
		StartToCloseTimeout: 1 * time.Minute,
	})
	var result string
	if err := workflow.ExecuteActivity(second, "echo").Get(second, &result); err != nil {
		return "", err
	}
	return result, nil
}

func main() {
	gorunner.Run(gorunner.RunArgs{
		Slug:             "harness-activity-basic-no-workflow-timeout",
		DefaultNamespace: "parity-activity-basic-no-workflow-timeout",
		DefaultTaskQueue: "TemporalTestsParityHarnessActivityBasicNoWorkflowTimeoutPhp",
		Workflows: []gorunner.Registration{
			{Fn: ActivityBasicNoWorkflowTimeoutWorkflow, Name: "Parity_Harness_ActivityBasicNoWorkflowTimeout"},
		},
		Activities: []gorunner.Registration{
			{Fn: EchoActivity, Name: "echo"},
		},
		Driver: func(ctx context.Context, c client.Client, workflowID, taskQueue string) (any, error) {
			run, err := c.ExecuteWorkflow(ctx, client.StartWorkflowOptions{
				ID:        workflowID,
				TaskQueue: taskQueue,
			}, "Parity_Harness_ActivityBasicNoWorkflowTimeout")
			if err != nil {
				return nil, err
			}
			var out string
			if err := run.Get(ctx, &out); err != nil {
				return nil, err
			}
			return out, nil
		},
		Expected: "echo",
	})
}
