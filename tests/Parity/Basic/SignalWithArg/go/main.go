package main

import (
	"context"
	"time"

	"go.temporal.io/sdk/client"
	"go.temporal.io/sdk/workflow"

	gorunner "github.com/temporalio/sdk-php/parity/go-runner"
)

func SignalWithArgWorkflow(ctx workflow.Context) (string, error) {
	signalChan := workflow.GetSignalChannel(ctx, "greet")
	var payload string
	signalChan.Receive(ctx, &payload)
	return "hi:" + payload, nil
}

func main() {
	gorunner.Run(gorunner.RunArgs{
		Slug:             "basic-signal-with-arg",
		DefaultNamespace: "parity-signal-with-arg",
		DefaultTaskQueue: "TemporalTestsParityBasicSignalWithArgPhp",
		Workflows: []gorunner.Registration{
			{Fn: SignalWithArgWorkflow, Name: "Parity_Basic_SignalWithArg"},
		},
		Driver: func(ctx context.Context, c client.Client, workflowID, taskQueue string) (any, error) {
			run, err := c.ExecuteWorkflow(ctx, client.StartWorkflowOptions{
				ID:        workflowID,
				TaskQueue: taskQueue,
			}, "Parity_Basic_SignalWithArg")
			if err != nil {
				return nil, err
			}
			time.Sleep(200 * time.Millisecond)
			if err := c.SignalWorkflow(ctx, workflowID, run.GetRunID(), "greet", "world"); err != nil {
				return nil, err
			}
			var out string
			if err := run.Get(ctx, &out); err != nil {
				return nil, err
			}
			return out, nil
		},
		Expected: "hi:world",
	})
}
