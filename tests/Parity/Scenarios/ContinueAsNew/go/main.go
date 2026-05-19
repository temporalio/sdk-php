package main

import (
	"context"
	"strconv"
	"time"

	"go.temporal.io/sdk/client"
	"go.temporal.io/sdk/workflow"

	gorunner "github.com/temporalio/sdk-php/parity/Runner/go-runner"
)

func CounterWorkflow(ctx workflow.Context, i int) (string, error) {
	if i < 2 {
		return "", workflow.NewContinueAsNewError(ctx, "Parity_Basic_ContinueAsNew", i+1)
	}
	return "done:" + strconv.Itoa(i), nil
}

func main() {
	var result string
	gorunner.RunCapturingFirstRun(gorunner.RunCapturingFirstRunArgs{
		Slug:             "continueasnew",
		DefaultNamespace: "parity-continue-as-new",
		DefaultTaskQueue: "TemporalTestsParityBasicContinueAsNewPhp",
		Workflows: []gorunner.Registration{
			{Fn: CounterWorkflow, Name: "Parity_Basic_ContinueAsNew"},
		},
		Timeout: 30 * time.Second,
		Starter: func(ctx context.Context, c client.Client, workflowID, taskQueue string) (client.WorkflowRun, error) {
			return c.ExecuteWorkflow(ctx, client.StartWorkflowOptions{
				ID:                       workflowID,
				TaskQueue:                taskQueue,
				WorkflowExecutionTimeout: 30 * time.Second,
			}, "Parity_Basic_ContinueAsNew", 0)
		},
		ResultPointer: &result,
		Expected:      "done:2",
	})
}
