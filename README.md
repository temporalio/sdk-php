# Temporal PHP SDK

Temporal is a distributed, scalable, durable, and highly available orchestration
engine used to execute asynchronous long-running business logic in a scalable
and resilient way.

Temporal PHP SDK is the framework for authoring [Workflows](https://docs.temporal.io/workflows) and [Activities](https://docs.temporal.io/activities) using PHP language.

**Table of contents:**
- [Get starting](#get-starting)
  - [Installation](#installation)
  - [Usage](#usage)
- [Testing](#testing)
- [Dev environment](#dev-environment)
  - [Temporal CLI](#temporal-cli)
  - [Buggregator](#buggregator)
- [Resources](#resources)
- [License](#license)

## Get starting

### Installation

Install the SDK using Composer:

```bash
composer require temporal/sdk
```

[![PHP](https://img.shields.io/packagist/php-v/temporal/sdk.svg?style=flat-square&logo=php)](https://packagist.org/packages/temporal/sdk)
[![Stable Release](https://poser.pugx.org/temporal/sdk/version?style=flat-square)](https://packagist.org/packages/temporal/sdk)
[![Total DLoads](https://img.shields.io/packagist/dt/temporal/sdk.svg?style=flat-square)](https://packagist.org/packages/temporal/sdk/stats)
[![License](https://img.shields.io/packagist/l/temporal/sdk.svg?style=flat-square)](LICENSE.md)

The SDK includes two main components: [Clients](https://docs.temporal.io/develop/php/temporal-clients) and Workers.  
The Clients component is used to start, schedule, and manage Workflows;
the Workers component is used to execute Workflows and Activities.

The Clients component requires the [`grpc`](https://pecl.php.net/package/grpc) extension,
and the Workers component requires [RoadRunner](https://roadrunner.dev).
It's recommended to use both components with the [`protobuf`](https://pecl.php.net/package/protobuf) extension
in production to improve performance.

|              | Client      | Worker      |
|--------------|-------------|-------------|
| RoadRunner   | —           | required    |
| ext-grpc     | required    | —           |
| ext-protobuf | recommended | recommended |

To download RoadRunner, you can use the following command:

```bash
./vendor/bin/rr get
```

### Usage

If you are using [Spiral](https://github.com/spiral/framework),
follow the [instructions in the documentation](https://spiral.dev/docs/temporal-configuration/).

If you are using the SDK without integrations, the following sections of the documentation may be helpful:
- [How to run Worker Processes](https://docs.temporal.io/develop/php/core-application#run-a-dev-worker)
- [How to develop a basic Workflow](https://docs.temporal.io/develop/php/core-application#develop-workflows)
- [How to connect a Temporal Client to a Temporal Service](https://docs.temporal.io/develop/php/temporal-clients#connect-to-a-dev-cluster)
- [How to start a Workflow Execution](https://docs.temporal.io/develop/php/temporal-clients#start-workflow-execution)

> [!NOTE]
> Check out [the repository with examples](https://github.com/temporalio/samples-php) of using the PHP SDK.

> [!WARNING]
> Since version [`2.11.0`](https://github.com/temporalio/sdk-php/releases/tag/v2.11.0),
> [feature flags](https://github.com/temporalio/sdk-php/blob/master/src/Worker/FeatureFlags.php) were introduced
> that change the behavior of the entire PHP worker.  
> It's recommended to disable deprecated behavior.

## Testing

The PHP SDK includes a toolkit for testing Workflows.
There is [documentation](https://docs.temporal.io/develop/php/testing-suite) and [dev guide](testing/Readme.md) on how to test a Workflow using Activity mocking.

To ensure the determinism of a Workflow,
you can also use the [Replay API in tests](https://docs.temporal.io/develop/php/testing-suite#replay).

## Dev environment

Some recommendations for setting up a development environment:

### Temporal CLI

The [Temporal CLI](https://docs.temporal.io/cli) provides direct access to a Temporal Service via the terminal.
You can use it to start, stop, inspect and operate on Workflows and Activities,
and perform administrative tasks such as Namespace, Schedule, and Task Queue management.
The Temporal CLI also includes an embedded Temporal Service suitable for use in development and CI/CD.
It includes the Temporal Server, SQLite persistence, and the Temporal Web UI.

Run the following command to start the Temporal Service in development mode:

```bash
temporal server start-dev --log-level error
```

Experimental features:
- Add flags `--dynamic-config-value frontend.enableUpdateWorkflowExecution=true --dynamic-config-value frontend.enableUpdateWorkflowExecutionAsyncAccepted=true`
to enable the [Workflow Update feature](https://docs.temporal.io/encyclopedia/workflow-message-passing#sending-updates).
- Add flag `--dynamic-config-value frontend.enableExecuteMultiOperation=true` to enable [`updateWithStart()` feature](https://php.temporal.io/classes/Temporal-Client-WorkflowClient.html#method_updateWithStart).
- Add flag `--dynamic-config-value system.enableEagerWorkflowStart=true` to enable the [Eager Workflow Start feature](https://docs.temporal.io/develop/advanced-start-options#eager-start).

### Buggregator

During development, you might need to dump a variable, throw an error trace, or simply look at the call stack.
Since Workflows and Activities run in RoadRunner workers, you cannot use `var_dump`,
`print_r`, `echo`, and other functions that output data to STDOUT.

Instead, use [Buggregator](https://buggregator.dev) along with [Trap](https://github.com/buggregator/trap).
In this case, dumps, traces, and logs will be sent via socket to your local Buggregator server,
where you can view them in a convenient web interface.

> [!TIP]
> Trap is a wrapper around `symfony/var-dumper`, providing additional debugging capabilities.
> Moreover, Trap patches var-dumper for outputting protobuf structures, which is very handy when working with Temporal.

To run Buggregator in Docker, execute the command below
and follow the [instructions](https://docs.buggregator.dev/config/var-dumper.html#configuration):

```bash
docker run --rm -p 8000:8000 -p 1025:1025 -p 9912:9912 -p 9913:9913 ghcr.io/buggregator/server:latest
```

If you are not using Docker or running PHP code outside a container, you can use Trap as a compact server:

```bash
./vendor/bin/trap --ui=8000
```

Now use the `trap()`, `tr()`, or `dump()` functions to output data to Buggregator.
Web UI will be available at `http://localhost:8000`.

## Resources

Read the docs  
[![Temporal Documentation](https://img.shields.io/static/v1?style=flat-square&label=&message=Dcumentation&logo=Temporal&color=7744ee)](https://docs.temporal.io/)
[![PHP SDK Documentation](https://img.shields.io/static/v1?style=flat-square&label=PHP+SDK&message=Dev+guide&logo=Temporal&color=7766ee)](https://docs.temporal.io/develop/php)
[![PHP SDK API](https://img.shields.io/static/v1?style=flat-square&label=PHP+SDK&message=API&logo=PHP&color=447723&logoColor=aa88ff)](https://php.temporal.io/)

Ask a question  
[![Github issues](https://img.shields.io/static/v1?style=flat-square&label=&message=Issues&logo=Github&color=202020)](https://github.com/temporalio/sdk-php/issues)
[![Slack](https://img.shields.io/static/v1?style=flat-square&label=&message=Slack&logo=Slack&color=cc4444)](https://t.mp/slack)
[![Forum](https://img.shields.io/static/v1?style=flat-square&label=&message=Forum&logo=Discourse&color=4477ee)](https://community.temporal.io/tag/php-sdk)
[![Discord](https://img.shields.io/static/v1?style=flat-square&label=&message=Discord&logo=Discord&color=333333)](https://discord.gg/FwmDtGQe55)

Stay tuned  
[![Read Blog](https://img.shields.io/static/v1?style=flat-square&label=&message=Read+the+Blog&logo=Temporal&color=312f2b)](https://temporal.io/blog)
[![Temporal YT Channel](https://img.shields.io/static/v1?style=flat-square&label=&message=Watch+on+Youtube&logo=youtube&color=b9002a)](https://www.youtube.com/temporalio)
[![X](https://img.shields.io/badge/-Follow-black?style=flat-square&logo=X)](https://x.com/temporalio)

Additionally  
[![Temporal community](https://img.shields.io/static/v1?style=flat-square&label=&message=Community&color=ff6644&logo=data:image/svg%2bxml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI5NyIgaGVpZ2h0PSI3NiI+PHBhdGggZD0iTTQyLjc0MDMgMTYuNTYyMkM0My43MjA0IDE3LjI3NzUgNDcuNjY3MyAyMC40Mjk3IDQ4LjY0NzQgMjEuMTk3OUM0OS42Mjc1IDIwLjM3NjcgNTMuNTc0NCAxNy4yNzc1IDU0LjU1NDUgMTYuNTYyMkM3NC41Mjc0IDEuNjIyMjggOTAuNTAwNSAtMi44MDE0MiA5NC45NTA3IDEuNjQ4NzhDOTkuNDAwOSA2LjA5ODk4IDk1LjAwMzcgMjIuMDQ1NSA4MC4wMzcyIDQyLjA0NUM3OS4zMjIgNDMuMDI1MSA3Ni4xNjk4IDQ2Ljk3MiA3NS40MDE2IDQ3Ljk1MjFDNzEuNjY2NiA1Mi40ODE3IDY3LjQ4MTMgNTcuMTk2OCA2Mi42ODY3IDYxLjk5MTRDNTcuODkyMSA2Ni43ODYgNTMuMjMgNzAuOTcxMyA0OC42NDc0IDc0LjcwNjNDNDQuMTE3NyA3MC45NzEzIDM5LjQwMjYgNjYuNzg2IDM0LjYwOCA2MS45OTE0QzI5LjgxMzUgNTcuMTk2OCAyNS42MjgyIDUyLjUzNDcgMjEuODkzMiA0Ny45NTIxQzIxLjA3MiA0Ni45NzIgMTcuOTcyOCA0My4wMjUxIDE3LjI1NzYgNDIuMDQ1QzIuMzE3NiAyMi4wNzIgLTIuMTA2MTEgNi4wOTg5OSAyLjM0NDA5IDEuNjQ4NzlDNi43OTQyOSAtMi44MDE0MSAyMi43NjczIDEuNjIyMjcgNDIuNzQwMyAxNi41NjIyWiIgZmlsbD0iI2ZmZiIvPjwvc3ZnPg==)](https://temporal.io/community)
[![Awesome Temporal](https://img.shields.io/static/v1?style=flat-square&label=&message=Awesome+Temporal&logo=Awesome-Lists&color=4b4567)](https://github.com/temporalio/awesome-temporal)


## License

Temporal PHP SDK is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

[![FOSSA Status](https://app.fossa.com/api/projects/git%2Bgithub.com%2Ftemporalio%2Fsdk-php.svg?type=large)](https://app.fossa.com/projects/git%2Bgithub.com%2Ftemporalio%2Fsdk-php?ref=badge_large)
