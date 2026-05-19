package main

import (
	"context"
	"time"

	"go.temporal.io/sdk/client"
	"go.temporal.io/sdk/workflow"

	gorunner "github.com/temporalio/sdk-php/parity/Runner/go-runner"
)

func GrandChildWorkflow(ctx workflow.Context, name string) (string, error) {
	return "g:" + name, nil
}

func ChildWorkflow(ctx workflow.Context, name string) (string, error) {
	ctx = workflow.WithChildOptions(ctx, workflow.ChildWorkflowOptions{
		WorkflowExecutionTimeout: 10 * time.Second,
	})
	var grandResult string
	if err := workflow.ExecuteChildWorkflow(ctx, "Parity_Basic_NestedChildWorkflow_GrandChild", name).Get(ctx, &grandResult); err != nil {
		return "", err
	}
	return "c:" + grandResult, nil
}

func ParentWorkflow(ctx workflow.Context) (string, error) {
	ctx = workflow.WithChildOptions(ctx, workflow.ChildWorkflowOptions{
		WorkflowExecutionTimeout: 10 * time.Second,
	})
	var childResult string
	if err := workflow.ExecuteChildWorkflow(ctx, "Parity_Basic_NestedChildWorkflow_Child", "hi").Get(ctx, &childResult); err != nil {
		return "", err
	}
	return "p:" + childResult, nil
}

func main() {
	gorunner.Run(gorunner.RunArgs{
		Slug:             "basic-nested-child-workflow",
		DefaultNamespace: "parity-nested-child-workflow",
		DefaultTaskQueue: "TemporalTestsParityBasicNestedChildWorkflowPhp",
		Workflows: []gorunner.Registration{
			{Fn: GrandChildWorkflow, Name: "Parity_Basic_NestedChildWorkflow_GrandChild"},
			{Fn: ChildWorkflow, Name: "Parity_Basic_NestedChildWorkflow_Child"},
			{Fn: ParentWorkflow, Name: "Parity_Basic_NestedChildWorkflow"},
		},
		Driver: func(ctx context.Context, c client.Client, workflowID, taskQueue string) (any, error) {
			run, err := c.ExecuteWorkflow(ctx, client.StartWorkflowOptions{
				ID:        workflowID,
				TaskQueue: taskQueue,
			}, "Parity_Basic_NestedChildWorkflow")
			if err != nil {
				return nil, err
			}
			var out string
			if err := run.Get(ctx, &out); err != nil {
				return nil, err
			}
			return out, nil
		},
		Expected: "p:c:g:hi",
	})
}
