package main

import (
	"context"
	"time"

	"go.temporal.io/sdk/client"
	"go.temporal.io/sdk/workflow"

	gorunner "github.com/temporalio/sdk-php/parity/go-runner"
)

func ChildWorkflow(ctx workflow.Context) (string, error) {
	var message string
	workflow.GetSignalChannel(ctx, "unblock-signal").Receive(ctx, &message)
	return message, nil
}

func ParentWorkflow(ctx workflow.Context) (string, error) {
	ctx = workflow.WithChildOptions(ctx, workflow.ChildWorkflowOptions{
		WorkflowExecutionTimeout: 10 * time.Second,
	})
	childFuture := workflow.ExecuteChildWorkflow(ctx, "Parity_Harness_ChildWorkflowSignal_Child")
	if err := childFuture.SignalChildWorkflow(ctx, "unblock-signal", "unblock").Get(ctx, nil); err != nil {
		return "", err
	}
	var result string
	if err := childFuture.Get(ctx, &result); err != nil {
		return "", err
	}
	return result, nil
}

func main() {
	gorunner.Run(gorunner.RunArgs{
		Slug:             "harness-child-workflow-signal",
		DefaultNamespace: "parity-child-workflow-signal",
		DefaultTaskQueue: "TemporalTestsParityHarnessChildWorkflowSignalPhp",
		Workflows: []gorunner.Registration{
			{Fn: ParentWorkflow, Name: "Parity_Harness_ChildWorkflowSignal_Parent"},
			{Fn: ChildWorkflow, Name: "Parity_Harness_ChildWorkflowSignal_Child"},
		},
		Driver: func(ctx context.Context, c client.Client, workflowID, taskQueue string) (any, error) {
			run, err := c.ExecuteWorkflow(ctx, client.StartWorkflowOptions{
				ID:        workflowID,
				TaskQueue: taskQueue,
			}, "Parity_Harness_ChildWorkflowSignal_Parent")
			if err != nil {
				return nil, err
			}
			var out string
			if err := run.Get(ctx, &out); err != nil {
				return nil, err
			}
			return out, nil
		},
		Expected: "unblock",
	})
}
