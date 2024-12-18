# Temporal PHP SDK

Temporal is a distributed, scalable, durable, and highly available orchestration
engine used to execute asynchronous long-running business logic in a scalable
and resilient way.

Temporal PHP SDK is the framework for authoring [Workflows](https://docs.temporal.io/workflows) and [Activities](https://docs.temporal.io/activities) using PHP language.

## Get starting

### Installation

Install the SDK using Composer:

```bash
composer require temporal/sdk
```

[![PHP](https://img.shields.io/packagist/php-v/temporal/sdk.svg?style=flat-square&logo=php)](https://packagist.org/packages/temporal/sdk)
[![Stable Release](https://poser.pugx.org/temporal/sdk/version?style=flat-square)](https://packagist.org/packages/temporal/sdk)
[![License](https://img.shields.io/packagist/l/temporal/sdk.svg?style=flat-square)](LICENSE.md)
[![Total DLoads](https://img.shields.io/packagist/dt/temporal/sdk.svg?style=flat-square)](https://packagist.org/packages/temporal/sdk/stats)

The SDK includes two main components: [Clients](https://docs.temporal.io/develop/php/temporal-clients) and Workers.
The Clients component is used to start, schedule, and manage Workflows;
the Workers component is used to execute Workflows and Activities.

The client part of the SDK requires the [`grpc` extension](https://pecl.php.net/package/grpc),
and the worker requires [RoadRunner](https://roadrunner.dev).
It is recommended to use both SDK components with the [`protobuf` extension](https://pecl.php.net/package/protobuf)
in production to improve performance.

|              | Client      | Worker      |
|--------------|-------------|-------------|
| RoadRunner   | —           | required    |
| ext-grpc     | required    | —           |
| ext-protobuf | recommended | recommended |

To download RoadRunner, you can use the following command:

```bash
vendor/bin/rr get
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
> Check out [the repository with examples](https://github.com/temporalio/samples-php) of using the SDK.

## Testing

The PHP SDK includes a toolkit for testing Workflows.
There is [documentation](https://docs.temporal.io/develop/php/testing-suite) and [dev guide](testing/Readme.md) on how to use it.

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

## Resources

Documentation  
[![Temporal Documentation](https://img.shields.io/static/v1?style=flat-square&label=&message=Dcumentation&logo=Temporal&color=%237744ee)](https://docs.temporal.io/)
[![PHP SDK Documentation](https://img.shields.io/static/v1?style=flat-square&label=PHP+SDK&message=Dev+guide&logo=Temporal&color=%237766ee)](https://docs.temporal.io/develop/php)
[![PHP SDK API](https://img.shields.io/static/v1?style=flat-square&label=PHP+SDK&message=API&logo=PHP&color=%23447723)](https://php.temporal.io/)

Ask a question  
[![Slack](https://img.shields.io/static/v1?style=flat-square&label=&message=Slack&logo=Slack&color=%23cc4444)](https://t.mp/slack/)
[![Forum](https://img.shields.io/static/v1?style=flat-square&label=&message=Forum&logo=Discourse&color=%234477ee)](https://community.temporal.io/)
[![Discord](https://img.shields.io/static/v1?style=flat-square&label=&message=Discord&logo=Discord&color=%23333333)](https://discord.gg/FwmDtGQe55)

Additional  
[![Awesome Temporal](https://img.shields.io/static/v1?style=flat-square&label=&message=Awesome+Temporal&logo=Awesome-Lists&color=%234b4567)](https://github.com/temporalio/awesome-temporal)
[![Temporal YT Channel](https://img.shields.io/static/v1?style=flat-square&label=&message=Watch+on+Youtube&logo=youtube&color=%23FF2052)](https://www.youtube.com/temporalio)


## License

Buggregator Trap is open-sourced software licensed under the [MIT License](https://opensource.org/licenses/MIT).

[![FOSSA Status](https://app.fossa.com/api/projects/git%2Bgithub.com%2Ftemporalio%2Fsdk-php.svg?type=large)](https://app.fossa.com/projects/git%2Bgithub.com%2Ftemporalio%2Fsdk-php?ref=badge_large)
