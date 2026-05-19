package main

import (
	"context"
	"strconv"

	"go.temporal.io/sdk/client"
	"go.temporal.io/sdk/workflow"

	gorunner "github.com/temporalio/sdk-php/parity/go-runner"
)

func SideEffectWorkflow(ctx workflow.Context) (string, error) {
	encoded := workflow.SideEffect(ctx, func(workflow.Context) any {
		return 42
	})
	var value int
	if err := encoded.Get(&value); err != nil {
		return "", err
	}
	return "value:" + strconv.Itoa(value), nil
}

func main() {
	gorunner.Run(gorunner.RunArgs{
		Slug:             "sideeffect",
		DefaultNamespace: "parity-side-effect",
		DefaultTaskQueue: "TemporalTestsParityBasicSideEffectPhp",
		Workflows: []gorunner.Registration{
			{Fn: SideEffectWorkflow, Name: "Parity_Basic_SideEffect"},
		},
		Driver: func(ctx context.Context, c client.Client, workflowID, taskQueue string) (any, error) {
			run, err := c.ExecuteWorkflow(ctx, client.StartWorkflowOptions{
				ID:        workflowID,
				TaskQueue: taskQueue,
			}, "Parity_Basic_SideEffect")
			if err != nil {
				return nil, err
			}
			var out string
			if err := run.Get(ctx, &out); err != nil {
				return nil, err
			}
			return out, nil
		},
		Expected: "value:42",
	})
}
