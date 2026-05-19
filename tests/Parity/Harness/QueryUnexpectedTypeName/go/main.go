package main

import (
	"context"
	"fmt"
	"os"
	"time"

	"go.temporal.io/sdk/client"
	"go.temporal.io/sdk/workflow"

	gorunner "github.com/temporalio/sdk-php/parity/go-runner"
)

func QueryUnexpectedTypeNameWorkflow(ctx workflow.Context) (string, error) {
	workflow.GetSignalChannel(ctx, "finish").Receive(ctx, nil)
	return "finished", nil
}

func main() {
	gorunner.Run(gorunner.RunArgs{
		Slug:             "harness-query-unexpected-type-name",
		DefaultNamespace: "parity-query-unexpected-type-name",
		DefaultTaskQueue: "TemporalTestsParityHarnessQueryUnexpectedTypeNamePhp",
		Workflows: []gorunner.Registration{
			{Fn: QueryUnexpectedTypeNameWorkflow, Name: "Parity_Harness_QueryUnexpectedTypeName"},
		},
		Driver: func(ctx context.Context, c client.Client, workflowID, taskQueue string) (any, error) {
			run, err := c.ExecuteWorkflow(ctx, client.StartWorkflowOptions{
				ID:        workflowID,
				TaskQueue: taskQueue,
			}, "Parity_Harness_QueryUnexpectedTypeName")
			if err != nil {
				return nil, err
			}

			time.Sleep(200 * time.Millisecond)

			_, queryErr := c.QueryWorkflow(ctx, workflowID, run.GetRunID(), "nonexistent")
			if queryErr == nil {
				fmt.Fprintln(os.Stderr, "[parity-go] expected error for unknown query name")
				os.Exit(2)
			}

			if err := c.SignalWorkflow(ctx, workflowID, run.GetRunID(), "finish", nil); err != nil {
				return nil, err
			}

			var out string
			if err := run.Get(ctx, &out); err != nil {
				return nil, err
			}
			return out, nil
		},
		Expected: "finished",
	})
}
