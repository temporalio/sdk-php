# Temporal PHP SDK

[![CI Status](https://github.com/temporalio/php-sdk/workflows/Unit/badge.svg)](https://github.com/temporalio/php-sdk/actions)
[![Stable Release](https://poser.pugx.org/temporal/sdk/version)](https://packagist.org/packages/temporal/sdk)
[![FOSSA Status](https://app.fossa.com/api/projects/git%2Bgithub.com%2Ftemporalio%2Fsdk-php.svg?type=shield)](https://app.fossa.com/projects/git%2Bgithub.com%2Ftemporalio%2Fsdk-php?ref=badge_shield)

## Introduction

Temporal is a distributed, scalable, durable, and highly available orchestration
engine used to execute asynchronous long-running business logic in a scalable
and resilient way.

"Temporal PHP SDK" is the framework for authoring workflows and activities using
PHP language.

## Installation

SDK is available as composer package and can be installed using the
following command in a root of your project:

```bash
composer require temporal/sdk
```

Make sure to install [RoadRunner](https://github.com/spiral/roadrunner) to enable workflow and activity consumption in your PHP workers.

## Usage

See [examples](https://github.com/temporalio/samples-php) to get started.

## Testing

See [testing manual](testing/Readme.md) to get started.

## Documentation
The documentation on how to use the Temporal PHP SDK and client is [here](https://docs.temporal.io/php/introduction).

## License
MIT License, please see [LICENSE](LICENSE.md) for details.


[![FOSSA Status](https://app.fossa.com/api/projects/git%2Bgithub.com%2Ftemporalio%2Fsdk-php.svg?type=large)](https://app.fossa.com/projects/git%2Bgithub.com%2Ftemporalio%2Fsdk-php?ref=badge_large)
