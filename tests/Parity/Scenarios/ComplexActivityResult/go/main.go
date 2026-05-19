package main

import (
	"context"
	"strings"
	"time"

	"go.temporal.io/sdk/client"
	"go.temporal.io/sdk/workflow"

	gorunner "github.com/temporalio/sdk-php/parity/Runner/go-runner"
)

func BagActivity(ctx context.Context) ([]string, error) {
	return []string{"alpha", "beta", "gamma"}, nil
}

func ComplexActivityResultWorkflow(ctx workflow.Context) (string, error) {
	ctx = workflow.WithActivityOptions(ctx, workflow.ActivityOptions{
		StartToCloseTimeout: 2 * time.Second,
	})
	var items []string
	if err := workflow.ExecuteActivity(ctx, "bag").Get(ctx, &items); err != nil {
		return "", err
	}
	return strings.Join(items, ":"), nil
}

func main() {
	gorunner.Run(gorunner.RunArgs{
		Slug:             "basic-complex-activity-result",
		DefaultNamespace: "parity-complex-activity-result",
		DefaultTaskQueue: "TemporalTestsParityBasicComplexActivityResultPhp",
		Workflows: []gorunner.Registration{
			{Fn: ComplexActivityResultWorkflow, Name: "Parity_Basic_ComplexActivityResult"},
		},
		Activities: []gorunner.Registration{
			{Fn: BagActivity, Name: "bag"},
		},
		Driver: func(ctx context.Context, c client.Client, workflowID, taskQueue string) (any, error) {
			run, err := c.ExecuteWorkflow(ctx, client.StartWorkflowOptions{
				ID:        workflowID,
				TaskQueue: taskQueue,
			}, "Parity_Basic_ComplexActivityResult")
			if err != nil {
				return nil, err
			}
			var out string
			if err := run.Get(ctx, &out); err != nil {
				return nil, err
			}
			return out, nil
		},
		Expected: "alpha:beta:gamma",
	})
}
