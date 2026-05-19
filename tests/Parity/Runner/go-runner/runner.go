package gorunner

import (
	"context"
	"fmt"
	"os"
	"reflect"
	"time"

	"github.com/google/uuid"
	"go.temporal.io/sdk/activity"
	"go.temporal.io/sdk/client"
	"go.temporal.io/sdk/worker"
	"go.temporal.io/sdk/workflow"
)

const defaultTimeout = 15 * time.Second

type Registration struct {
	Fn   any
	Name string
}

type Driver func(ctx context.Context, c client.Client, workflowID, taskQueue string) (any, error)

type AsyncStarter func(ctx context.Context, c client.Client, workflowID, taskQueue string) (client.WorkflowRun, error)

type RunArgs struct {
	Slug             string
	DefaultNamespace string
	DefaultTaskQueue string
	Workflows        []Registration
	Activities       []Registration
	Timeout          time.Duration
	Driver           Driver
	Expected         any
}

type RunCapturingFirstRunArgs struct {
	Slug             string
	DefaultNamespace string
	DefaultTaskQueue string
	Workflows        []Registration
	Activities       []Registration
	Timeout          time.Duration
	Starter          AsyncStarter
	ResultPointer    any
	Expected         any
}

// Run dispatches on --mode: worker process registers workflows + activities and
// blocks on InterruptCh; starter process executes the workflow and exits. Shell
// scripts coordinate the two (worker in background, starter in foreground; SIGTERM
// worker after starter exits). This mirrors temporalio/samples-java and avoids
// the in-JVM "halt mid-poll" race that flaked shared-task-queue runs.
func Run(args RunArgs) {
	parsed := ParseArgs(os.Args[1:], args.DefaultNamespace, args.DefaultTaskQueue)
	switch parsed.Mode {
	case ModeWorker:
		runWorker(parsed, args.Workflows, args.Activities)
	case ModeStarter:
		runStarter(parsed, args)
	}
}

func RunCapturingFirstRun(args RunCapturingFirstRunArgs) {
	parsed := ParseArgs(os.Args[1:], args.DefaultNamespace, args.DefaultTaskQueue)
	switch parsed.Mode {
	case ModeWorker:
		runWorker(parsed, args.Workflows, args.Activities)
	case ModeStarter:
		runStarterCapturingFirstRun(parsed, args)
	}
}

func runWorker(parsed Args, workflows, activities []Registration) {
	c, err := client.Dial(client.Options{
		HostPort:  parsed.Address,
		Namespace: parsed.Namespace,
	})
	if err != nil {
		fail("client.Dial: %v", err)
	}
	defer c.Close()

	w := worker.New(c, parsed.TaskQueue, worker.Options{})
	for _, registration := range workflows {
		w.RegisterWorkflowWithOptions(registration.Fn, workflow.RegisterOptions{Name: registration.Name})
	}
	for _, registration := range activities {
		w.RegisterActivityWithOptions(registration.Fn, activity.RegisterOptions{Name: registration.Name})
	}

	if err := w.Start(); err != nil {
		fail("worker.Start: %v", err)
	}
	defer w.Stop()

	fmt.Println("WORKER_READY")
	_ = os.Stdout.Sync()

	<-worker.InterruptCh()
}

func runStarter(parsed Args, args RunArgs) {
	c, err := client.Dial(client.Options{
		HostPort:  parsed.Address,
		Namespace: parsed.Namespace,
	})
	if err != nil {
		fail("client.Dial: %v", err)
	}
	defer c.Close()

	timeout := args.Timeout
	if timeout <= 0 {
		timeout = defaultTimeout
	}
	ctx, cancel := context.WithTimeout(context.Background(), timeout+10*time.Second)
	defer cancel()

	workflowID := "parity-" + args.Slug + "-go-" + uuid.NewString()
	result, err := args.Driver(ctx, c, workflowID, parsed.TaskQueue)
	if err != nil {
		fail("driver returned error: %v", err)
	}
	if !reflect.DeepEqual(args.Expected, result) {
		fmt.Fprintf(os.Stderr, "[parity-go] unexpected: got=%#v want=%#v\n", result, args.Expected)
		_ = os.Stderr.Sync()
		os.Exit(2)
	}

	fmt.Println("WORKFLOW_ID=" + workflowID)
	_ = os.Stdout.Sync()
}

func runStarterCapturingFirstRun(parsed Args, args RunCapturingFirstRunArgs) {
	c, err := client.Dial(client.Options{
		HostPort:  parsed.Address,
		Namespace: parsed.Namespace,
	})
	if err != nil {
		fail("client.Dial: %v", err)
	}
	defer c.Close()

	timeout := args.Timeout
	if timeout <= 0 {
		timeout = defaultTimeout
	}
	ctx, cancel := context.WithTimeout(context.Background(), timeout+10*time.Second)
	defer cancel()

	workflowID := "parity-" + args.Slug + "-go-" + uuid.NewString()
	run, err := args.Starter(ctx, c, workflowID, parsed.TaskQueue)
	if err != nil {
		fail("starter returned error: %v", err)
	}

	fmt.Println("WORKFLOW_ID=" + workflowID)
	fmt.Println("RUN_ID=" + run.GetRunID())
	_ = os.Stdout.Sync()

	if err := run.Get(ctx, args.ResultPointer); err != nil {
		fail("workflow get returned error: %v", err)
	}
	result := reflect.ValueOf(args.ResultPointer).Elem().Interface()
	if !reflect.DeepEqual(args.Expected, result) {
		fmt.Fprintf(os.Stderr, "[parity-go] unexpected: got=%#v want=%#v\n", result, args.Expected)
		_ = os.Stderr.Sync()
		os.Exit(2)
	}
}

func fail(format string, args ...any) {
	fmt.Fprintf(os.Stderr, "[parity-go] "+format+"\n", args...)
	_ = os.Stderr.Sync()
	os.Exit(1)
}
