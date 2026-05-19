package main

import (
	"context"
	"time"

	"go.temporal.io/sdk/client"
	"go.temporal.io/sdk/workflow"

	gorunner "github.com/temporalio/sdk-php/parity/Runner/go-runner"
)

func ChildWorkflow(ctx workflow.Context, name string) (string, error) {
	return "child-said:" + name, nil
}

func ParentWorkflow(ctx workflow.Context) (string, error) {
	ctx = workflow.WithChildOptions(ctx, workflow.ChildWorkflowOptions{
		WorkflowExecutionTimeout: 10 * time.Second,
	})
	var childResult string
	if err := workflow.ExecuteChildWorkflow(ctx, "Parity_Basic_ChildWorkflow_Child", "hello").Get(ctx, &childResult); err != nil {
		return "", err
	}
	return "parent-got:" + childResult, nil
}

func main() {
	gorunner.Run(gorunner.RunArgs{
		Slug:             "childworkflow-parent",
		DefaultNamespace: "parity-child-workflow",
		DefaultTaskQueue: "TemporalTestsParityBasicChildWorkflowPhp",
		Workflows: []gorunner.Registration{
			{Fn: ChildWorkflow, Name: "Parity_Basic_ChildWorkflow_Child"},
			{Fn: ParentWorkflow, Name: "Parity_Basic_ChildWorkflow_Parent"},
		},
		Driver: func(ctx context.Context, c client.Client, workflowID, taskQueue string) (any, error) {
			run, err := c.ExecuteWorkflow(ctx, client.StartWorkflowOptions{
				ID:        workflowID,
				TaskQueue: taskQueue,
			}, "Parity_Basic_ChildWorkflow_Parent")
			if err != nil {
				return nil, err
			}
			var out string
			if err := run.Get(ctx, &out); err != nil {
				return nil, err
			}
			return out, nil
		},
		Expected: "parent-got:child-said:hello",
	})
}
