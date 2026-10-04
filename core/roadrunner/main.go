package main

import (
	"flag"
	"fmt"
	"log/slog"
	"os"
	"os/signal"
	"syscall"
	"time"

	appLogger "github.com/roadrunner-server/app-logger/v5"
	configPlugin "github.com/roadrunner-server/config/v5"
	"github.com/roadrunner-server/endure/v2"
	"github.com/roadrunner-server/informer/v5"
	"github.com/roadrunner-server/jobs/v5"
	"github.com/roadrunner-server/kv/v5"
	"github.com/roadrunner-server/lock/v5"
	"github.com/roadrunner-server/logger/v5"
	"github.com/roadrunner-server/memory/v5"
	"github.com/roadrunner-server/metrics/v5"
	"github.com/roadrunner-server/resetter/v5"
	rpcPlugin "github.com/roadrunner-server/rpc/v5"
	"github.com/roadrunner-server/server/v5"
	"github.com/roadrunner-server/service/v5"
	"github.com/roadrunner-server/status/v5"
)

const gracePeriod = 30 * time.Second

type overrides []string

func (o *overrides) String() string { return fmt.Sprint(*o) }

func (o *overrides) Set(value string) error {
	*o = append(*o, value)
	return nil
}

func main() {
	if err := run(os.Args[1:]); err != nil {
		fmt.Fprintln(os.Stderr, err)
		os.Exit(1)
	}
}

func run(args []string) error {
	if len(args) > 0 && args[0] == "serve" {
		args = args[1:]
	}
	flags := flag.NewFlagSet("rr", flag.ContinueOnError)
	configFile := flags.String("c", ".rr.yaml", "config file")
	workDir := flags.String("w", "", "working directory")
	var override overrides
	flags.Var(&override, "o", "config override, key=value")
	if err := flags.Parse(args); err != nil {
		return err
	}
	if *workDir != "" {
		if err := os.Chdir(*workDir); err != nil {
			return err
		}
	}

	container := endure.New(slog.LevelError, endure.GracefulShutdownTimeout(gracePeriod))
	err := container.RegisterAll(
		&configPlugin.Plugin{Path: *configFile, Timeout: gracePeriod, Flags: override},
		&informer.Plugin{},
		&resetter.Plugin{},
		&rpcPlugin.Plugin{},
		&logger.Plugin{},
		&appLogger.Plugin{},
		&server.Plugin{},
		&service.Plugin{},
		&memory.Plugin{},
		&kv.Plugin{},
		&jobs.Plugin{},
		&lock.Plugin{},
		&metrics.Plugin{},
		&status.Plugin{},
	)
	if err != nil {
		return err
	}
	if err = container.Init(); err != nil {
		return err
	}
	errCh, err := container.Serve()
	if err != nil {
		return err
	}

	stop := make(chan os.Signal, 1)
	signal.Notify(stop, syscall.SIGINT, syscall.SIGTERM)
	select {
	case e := <-errCh:
		return fmt.Errorf("plugin %s: %w", e.VertexID, e.Error)
	case <-stop:
		return container.Stop()
	}
}
