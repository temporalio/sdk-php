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

func QueryUnexpectedReturnTypeWorkflow(ctx workflow.Context) (string, error) {
	if err := workflow.SetQueryHandler(ctx, "the_query", func() (string, error) {
		return "hi bob", nil
	}); err != nil {
		return "", err
	}
	workflow.GetSignalChannel(ctx, "finish").Receive(ctx, nil)
	return "finished", nil
}

func main() {
	gorunner.Run(gorunner.RunArgs{
		Slug:             "harness-query-unexpected-return-type",
		DefaultNamespace: "parity-query-unexpected-return-type",
		DefaultTaskQueue: "TemporalTestsParityHarnessQueryUnexpectedReturnTypePhp",
		Workflows: []gorunner.Registration{
			{Fn: QueryUnexpectedReturnTypeWorkflow, Name: "Parity_Harness_QueryUnexpectedReturnType"},
		},
		Driver: func(ctx context.Context, c client.Client, workflowID, taskQueue string) (any, error) {
			run, err := c.ExecuteWorkflow(ctx, client.StartWorkflowOptions{
				ID:        workflowID,
				TaskQueue: taskQueue,
			}, "Parity_Harness_QueryUnexpectedReturnType")
			if err != nil {
				return nil, err
			}

			time.Sleep(200 * time.Millisecond)

			queryResult, err := c.QueryWorkflow(ctx, workflowID, run.GetRunID(), "the_query")
			if err != nil {
				return nil, err
			}
			var intResult int
			caught := false
			if decodeErr := queryResult.Get(&intResult); decodeErr != nil {
				caught = true
			}
			if !caught {
				fmt.Fprintf(os.Stderr, "[parity-go] expected decoding error, got int=%d\n", intResult)
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
