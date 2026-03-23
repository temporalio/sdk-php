<?php

declare(strict_types=1);

namespace Temporal\Common\EnvConfig\Exception;

/**
 * Thrown when codec configuration is provided but not supported by the PHP SDK.
 *
 * Per the Temporal external client configuration specification, PHP SDK (like TypeScript, Python, and .NET)
 * does not support remote codec configuration. This exception is raised to prevent silent failures
 * when users expect codec functionality.
 *
 * @link https://github.com/temporalio/proposals/blob/master/all-sdk/external-client-configuration.md
 */
final class CodecNotSupportedException extends ConfigException
{
    public function __construct()
    {
        parent::__construct(
            'Remote codec configuration is not supported in the PHP SDK. ' .
            'Please remove codec settings from your configuration file or environment variables.',
        );
    }
}
