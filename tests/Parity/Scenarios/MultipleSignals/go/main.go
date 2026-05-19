package main

import (
	"context"
	"strings"
	"time"

	"go.temporal.io/sdk/client"
	"go.temporal.io/sdk/workflow"

	gorunner "github.com/temporalio/sdk-php/parity/Runner/go-runner"
)

func MultipleSignalsWorkflow(ctx workflow.Context) (string, error) {
	signalChan := workflow.GetSignalChannel(ctx, "push")
	buffer := make([]string, 0, 3)
	for len(buffer) < 3 {
		var value string
		signalChan.Receive(ctx, &value)
		buffer = append(buffer, value)
	}
	return strings.Join(buffer, "|"), nil
}

func main() {
	gorunner.Run(gorunner.RunArgs{
		Slug:             "basic-multiple-signals",
		DefaultNamespace: "parity-multiple-signals",
		DefaultTaskQueue: "TemporalTestsParityBasicMultipleSignalsPhp",
		Workflows: []gorunner.Registration{
			{Fn: MultipleSignalsWorkflow, Name: "Parity_Basic_MultipleSignals"},
		},
		Driver: func(ctx context.Context, c client.Client, workflowID, taskQueue string) (any, error) {
			run, err := c.ExecuteWorkflow(ctx, client.StartWorkflowOptions{
				ID:        workflowID,
				TaskQueue: taskQueue,
			}, "Parity_Basic_MultipleSignals")
			if err != nil {
				return nil, err
			}
			for _, value := range []string{"a", "b", "c"} {
				time.Sleep(150 * time.Millisecond)
				if err := c.SignalWorkflow(ctx, workflowID, run.GetRunID(), "push", value); err != nil {
					return nil, err
				}
			}
			var out string
			if err := run.Get(ctx, &out); err != nil {
				return nil, err
			}
			return out, nil
		},
		Expected: "a|b|c",
	})
}
