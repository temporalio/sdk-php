package main

import (
	"context"
	"time"

	"go.temporal.io/sdk/client"
	"go.temporal.io/sdk/workflow"

	gorunner "github.com/temporalio/sdk-php/parity/go-runner"
)

func SayActivity(ctx context.Context, word string) (string, error) {
	return "echoed:" + word, nil
}

func MultipleActivitiesWorkflow(ctx workflow.Context) (string, error) {
	ctx = workflow.WithActivityOptions(ctx, workflow.ActivityOptions{
		StartToCloseTimeout: 5 * time.Second,
	})
	var a, b, c string
	if err := workflow.ExecuteActivity(ctx, "say", "one").Get(ctx, &a); err != nil {
		return "", err
	}
	if err := workflow.ExecuteActivity(ctx, "say", "two").Get(ctx, &b); err != nil {
		return "", err
	}
	if err := workflow.ExecuteActivity(ctx, "say", "three").Get(ctx, &c); err != nil {
		return "", err
	}
	return a + "|" + b + "|" + c, nil
}

func main() {
	gorunner.Run(gorunner.RunArgs{
		Slug:             "multipleactivities",
		DefaultNamespace: "parity-multiple-activities",
		DefaultTaskQueue: "TemporalTestsParityBasicMultipleActivitiesPhp",
		Workflows: []gorunner.Registration{
			{Fn: MultipleActivitiesWorkflow, Name: "Parity_Basic_MultipleActivities"},
		},
		Activities: []gorunner.Registration{
			{Fn: SayActivity, Name: "say"},
		},
		Driver: func(ctx context.Context, c client.Client, workflowID, taskQueue string) (any, error) {
			run, err := c.ExecuteWorkflow(ctx, client.StartWorkflowOptions{
				ID:        workflowID,
				TaskQueue: taskQueue,
			}, "Parity_Basic_MultipleActivities")
			if err != nil {
				return nil, err
			}
			var out string
			if err := run.Get(ctx, &out); err != nil {
				return nil, err
			}
			return out, nil
		},
		Expected: "echoed:one|echoed:two|echoed:three",
	})
}
