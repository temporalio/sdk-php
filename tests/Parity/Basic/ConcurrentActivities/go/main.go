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

func ConcurrentActivitiesWorkflow(ctx workflow.Context) (string, error) {
	ctx = workflow.WithActivityOptions(ctx, workflow.ActivityOptions{
		StartToCloseTimeout: 5 * time.Second,
	})

	futureA := workflow.ExecuteActivity(ctx, "say", "a")
	futureB := workflow.ExecuteActivity(ctx, "say", "b")
	futureC := workflow.ExecuteActivity(ctx, "say", "c")

	var a, b, c string
	if err := futureA.Get(ctx, &a); err != nil {
		return "", err
	}
	if err := futureB.Get(ctx, &b); err != nil {
		return "", err
	}
	if err := futureC.Get(ctx, &c); err != nil {
		return "", err
	}
	return a + "|" + b + "|" + c, nil
}

func main() {
	gorunner.Run(gorunner.RunArgs{
		Slug:             "concurrentactivities",
		DefaultNamespace: "parity-concurrent-activities",
		DefaultTaskQueue: "TemporalTestsParityBasicConcurrentActivitiesPhp",
		Workflows: []gorunner.Registration{
			{Fn: ConcurrentActivitiesWorkflow, Name: "Parity_Basic_ConcurrentActivities"},
		},
		Activities: []gorunner.Registration{
			{Fn: SayActivity, Name: "say"},
		},
		Driver: func(ctx context.Context, c client.Client, workflowID, taskQueue string) (any, error) {
			run, err := c.ExecuteWorkflow(ctx, client.StartWorkflowOptions{
				ID:        workflowID,
				TaskQueue: taskQueue,
			}, "Parity_Basic_ConcurrentActivities")
			if err != nil {
				return nil, err
			}
			var out string
			if err := run.Get(ctx, &out); err != nil {
				return nil, err
			}
			return out, nil
		},
		Expected: "echoed:a|echoed:b|echoed:c",
	})
}
