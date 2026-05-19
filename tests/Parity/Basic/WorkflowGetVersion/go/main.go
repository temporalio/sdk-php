package main

import (
	"context"
	"strconv"

	"go.temporal.io/sdk/client"
	"go.temporal.io/sdk/workflow"

	gorunner "github.com/temporalio/sdk-php/parity/go-runner"
)

func GetVersionWorkflow(ctx workflow.Context) (string, error) {
	version := workflow.GetVersion(ctx, "change-a", 1, 2)
	return "v:" + strconv.Itoa(int(version)), nil
}

func main() {
	gorunner.Run(gorunner.RunArgs{
		Slug:             "basic-workflow-get-version",
		DefaultNamespace: "parity-workflow-get-version",
		DefaultTaskQueue: "TemporalTestsParityBasicWorkflowGetVersionPhp",
		Workflows: []gorunner.Registration{
			{Fn: GetVersionWorkflow, Name: "Parity_Basic_WorkflowGetVersion"},
		},
		Driver: func(ctx context.Context, c client.Client, workflowID, taskQueue string) (any, error) {
			run, err := c.ExecuteWorkflow(ctx, client.StartWorkflowOptions{
				ID:        workflowID,
				TaskQueue: taskQueue,
			}, "Parity_Basic_WorkflowGetVersion")
			if err != nil {
				return nil, err
			}
			var out string
			if err := run.Get(ctx, &out); err != nil {
				return nil, err
			}
			return out, nil
		},
		Expected: "v:2",
	})
}
