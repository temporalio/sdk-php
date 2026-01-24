<?php

declare(strict_types=1);

namespace Temporal\Common\EnvConfig\Exception;

/**
 * Thrown when a configuration file contains duplicate profile names (case-insensitive).
 *
 * Per specification: "It is a validation error to have a config file with two separately-cased
 * profile names that are equal case-insensitively."
 */
final class DuplicateProfileException extends ConfigException
{
    public function __construct(
        public readonly string $profileName,
        public readonly string $existingName,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            "Duplicate profile name (case-insensitive): '{$profileName}' conflicts with existing '{$existingName}'.",
            0,
            $previous,
        );
    }
}
