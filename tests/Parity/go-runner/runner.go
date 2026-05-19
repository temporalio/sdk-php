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

type setup struct {
	client     client.Client
	worker     worker.Worker
	workflowID string
	taskQueue  string
	timeout    time.Duration
}

func Run(args RunArgs) {
	s := boot(args.Slug, args.DefaultNamespace, args.DefaultTaskQueue,
		args.Workflows, args.Activities, args.Timeout)
	defer s.client.Close()
	defer s.worker.Stop()

	ctx, cancel := context.WithTimeout(context.Background(), s.timeout+10*time.Second)
	defer cancel()

	result, err := args.Driver(ctx, s.client, s.workflowID, s.taskQueue)
	if err != nil {
		fail("driver returned error: %v", err)
	}

	assertExpected(args.Expected, result)
	emitWorkflowID(s.workflowID)
	os.Exit(0)
}

func RunCapturingFirstRun(args RunCapturingFirstRunArgs) {
	s := boot(args.Slug, args.DefaultNamespace, args.DefaultTaskQueue,
		args.Workflows, args.Activities, args.Timeout)
	defer s.client.Close()
	defer s.worker.Stop()

	ctx, cancel := context.WithTimeout(context.Background(), s.timeout+10*time.Second)
	defer cancel()

	run, err := args.Starter(ctx, s.client, s.workflowID, s.taskQueue)
	if err != nil {
		fail("starter returned error: %v", err)
	}

	fmt.Println("WORKFLOW_ID=" + s.workflowID)
	fmt.Println("RUN_ID=" + run.GetRunID())
	_ = os.Stdout.Sync()

	if err := run.Get(ctx, args.ResultPointer); err != nil {
		fail("workflow get returned error: %v", err)
	}

	result := reflect.ValueOf(args.ResultPointer).Elem().Interface()
	assertExpected(args.Expected, result)
	os.Exit(0)
}

func boot(slug, defaultNamespace, defaultTaskQueue string,
	workflows, activities []Registration, timeout time.Duration,
) *setup {
	parsed := ParseArgs(os.Args[1:], defaultNamespace, defaultTaskQueue)

	c, err := client.Dial(client.Options{
		HostPort:  parsed.Address,
		Namespace: parsed.Namespace,
	})
	if err != nil {
		fail("client.Dial: %v", err)
	}

	w := worker.New(c, parsed.TaskQueue, worker.Options{})
	for _, registration := range workflows {
		w.RegisterWorkflowWithOptions(registration.Fn, workflow.RegisterOptions{Name: registration.Name})
	}
	for _, registration := range activities {
		w.RegisterActivityWithOptions(registration.Fn, activity.RegisterOptions{Name: registration.Name})
	}
	if err := w.Start(); err != nil {
		c.Close()
		fail("worker.Start: %v", err)
	}

	if timeout <= 0 {
		timeout = defaultTimeout
	}

	return &setup{
		client:     c,
		worker:     w,
		workflowID: "parity-" + slug + "-go-" + uuid.NewString(),
		taskQueue:  parsed.TaskQueue,
		timeout:    timeout,
	}
}

func assertExpected(expected, actual any) {
	if reflect.DeepEqual(expected, actual) {
		return
	}
	fmt.Fprintf(os.Stderr, "[parity-go] unexpected: got=%#v want=%#v\n", actual, expected)
	_ = os.Stderr.Sync()
	os.Exit(2)
}

func emitWorkflowID(workflowID string) {
	fmt.Println("WORKFLOW_ID=" + workflowID)
	_ = os.Stdout.Sync()
}

func fail(format string, args ...any) {
	fmt.Fprintf(os.Stderr, "[parity-go] "+format+"\n", args...)
	_ = os.Stderr.Sync()
	os.Exit(1)
}
