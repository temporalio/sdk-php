package main

import (
	"context"
	"fmt"
	"os"
	"time"

	"go.temporal.io/sdk/client"
	"go.temporal.io/sdk/workflow"

	gorunner "github.com/temporalio/sdk-php/parity/Runner/go-runner"
)

func QueryUnexpectedArgumentsWorkflow(ctx workflow.Context) (string, error) {
	if err := workflow.SetQueryHandler(ctx, "the_query", func(arg int) (string, error) {
		return fmt.Sprintf("got %d", arg), nil
	}); err != nil {
		return "", err
	}

	workflow.GetSignalChannel(ctx, "finish").Receive(ctx, nil)
	return "finished", nil
}

func main() {
	gorunner.Run(gorunner.RunArgs{
		Slug:             "harness-query-unexpected-arguments",
		DefaultNamespace: "parity-query-unexpected-arguments",
		DefaultTaskQueue: "TemporalTestsParityHarnessQueryUnexpectedArgumentsPhp",
		Workflows: []gorunner.Registration{
			{Fn: QueryUnexpectedArgumentsWorkflow, Name: "Parity_Harness_QueryUnexpectedArguments"},
		},
		Driver: func(ctx context.Context, c client.Client, workflowID, taskQueue string) (any, error) {
			run, err := c.ExecuteWorkflow(ctx, client.StartWorkflowOptions{
				ID:        workflowID,
				TaskQueue: taskQueue,
			}, "Parity_Harness_QueryUnexpectedArguments")
			if err != nil {
				return nil, err
			}

			time.Sleep(200 * time.Millisecond)

			queryResult, err := c.QueryWorkflow(ctx, workflowID, run.GetRunID(), "the_query", 42)
			if err != nil {
				return nil, err
			}
			var queryValue string
			if err := queryResult.Get(&queryValue); err != nil {
				return nil, err
			}
			if queryValue != "got 42" {
				fmt.Fprintf(os.Stderr, "[parity-go] unexpected query result: %q\n", queryValue)
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
