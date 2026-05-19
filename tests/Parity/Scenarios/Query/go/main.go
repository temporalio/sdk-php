package main

import (
	"context"
	"fmt"
	"os"
	"strconv"
	"time"

	"go.temporal.io/sdk/client"
	"go.temporal.io/sdk/workflow"

	gorunner "github.com/temporalio/sdk-php/parity/Runner/go-runner"
)

func QueryWorkflow(ctx workflow.Context) (string, error) {
	counter := 0
	done := false

	if err := workflow.SetQueryHandler(ctx, "get_counter", func() (int, error) {
		return counter, nil
	}); err != nil {
		return "", err
	}

	workflow.Go(ctx, func(gctx workflow.Context) {
		bumpChan := workflow.GetSignalChannel(gctx, "bump")
		for {
			bumpChan.Receive(gctx, nil)
			counter++
		}
	})

	workflow.GetSignalChannel(ctx, "finish").Receive(ctx, nil)
	done = true
	_ = done

	return "final:" + strconv.Itoa(counter), nil
}

func main() {
	gorunner.Run(gorunner.RunArgs{
		Slug:             "basic-query",
		DefaultNamespace: "parity-query",
		DefaultTaskQueue: "TemporalTestsParityBasicQueryPhp",
		Workflows: []gorunner.Registration{
			{Fn: QueryWorkflow, Name: "Parity_Basic_Query"},
		},
		Driver: func(ctx context.Context, c client.Client, workflowID, taskQueue string) (any, error) {
			run, err := c.ExecuteWorkflow(ctx, client.StartWorkflowOptions{
				ID:        workflowID,
				TaskQueue: taskQueue,
			}, "Parity_Basic_Query")
			if err != nil {
				return nil, err
			}

			time.Sleep(150 * time.Millisecond)
			if err := c.SignalWorkflow(ctx, workflowID, run.GetRunID(), "bump", nil); err != nil {
				return nil, err
			}
			time.Sleep(150 * time.Millisecond)
			if err := c.SignalWorkflow(ctx, workflowID, run.GetRunID(), "bump", nil); err != nil {
				return nil, err
			}
			time.Sleep(150 * time.Millisecond)

			queryResult, err := c.QueryWorkflow(ctx, workflowID, run.GetRunID(), "get_counter")
			if err != nil {
				return nil, err
			}
			var counter int
			if err := queryResult.Get(&counter); err != nil {
				return nil, err
			}
			if counter != 2 {
				fmt.Fprintf(os.Stderr, "[parity-go] unexpected counter: %d\n", counter)
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
		Expected: "final:2",
	})
}
