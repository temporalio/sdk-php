<?php

declare(strict_types=1);

namespace Temporal\Common\EnvConfig\Exception;

/**
 * Thrown when the TOML parser package is not found.
 */
final class TomlParserNotFoundException extends ConfigException
{
    public function __construct()
    {
        parent::__construct(
            'The package for parsing TOML files "internal/toml" is not found. ' .
            'Please install it via Composer: `composer require internal/toml`.',
        );
    }
}
