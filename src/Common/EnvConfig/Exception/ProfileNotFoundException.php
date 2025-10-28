<?php

declare(strict_types=1);

namespace Temporal\Common\EnvConfig\Exception;

/**
 * Thrown when a requested profile is not found in the configuration.
 */
final class ProfileNotFoundException extends ConfigException
{
    public function __construct(
        public readonly string $profileName,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            "Profile '{$profileName}' not found in configuration.",
            0,
            $previous,
        );
    }
}
