# Temporal PHP SDK

[![CI Status](https://github.com/temporalio/php-sdk/workflows/CI/badge.svg)](https://github.com/temporalio/php-sdk/actions)

**Attention, the package is under development.**

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
$ composer require temporal/sdk
```

> Please note that this installation method is currently NOT AVAILABLE, please 
> use the follow way instead

### Temporary Installation Way

Add the following lines to your `composer.json` file and 
then execute `composer update` command:

```json5
{
    "repositories": [
        // Add git repository as "temporal/sdk" dependency
        {
            "type":"package",
            "package": {
              "name": "temporal/sdk",
              "version": "0.1",
              "source": {
                  "url": "https://github.com/temporalio/php-sdk.git",
                  "type": "git",
                  "reference":"master"
                }
            }
        }
    ],
    "require": {
        // Add dependency on SDK
        "temporal/sdk": "^0.1"
    }
}
```

## Usage

See [example](/example) to get started.

No documentation available at this time.
