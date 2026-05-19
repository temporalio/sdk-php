package main

import (
	"context"

	"go.temporal.io/sdk/client"
	"go.temporal.io/sdk/workflow"

	gorunner "github.com/temporalio/sdk-php/parity/Runner/go-runner"
)

func HelloWorldWorkflow(ctx workflow.Context, name string) (string, error) {
	return "hello, " + name + "!", nil
}

func main() {
	gorunner.Run(gorunner.RunArgs{
		Slug:             "helloworld",
		DefaultNamespace: "parity-helloworld",
		DefaultTaskQueue: `Temporal\Tests\Parity\Basic\HelloWorld\Php`,
		Workflows: []gorunner.Registration{
			{Fn: HelloWorldWorkflow, Name: "Parity_Basic_HelloWorld"},
		},
		Driver: func(ctx context.Context, c client.Client, workflowID, taskQueue string) (any, error) {
			run, err := c.ExecuteWorkflow(ctx, client.StartWorkflowOptions{
				ID:        workflowID,
				TaskQueue: taskQueue,
			}, "Parity_Basic_HelloWorld", "world")
			if err != nil {
				return nil, err
			}
			var out string
			if err := run.Get(ctx, &out); err != nil {
				return nil, err
			}
			return out, nil
		},
		Expected: "hello, world!",
	})
}
