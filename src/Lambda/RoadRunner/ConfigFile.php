<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Lambda\RoadRunner;

use Temporal\Lambda\Config;
use Temporal\Lambda\Exception\ConfigurationException;

final class ConfigFile
{
    public const PATH = '/tmp/.rr.yaml';

    public static function render(Config $config): string
    {
        $template = \file_get_contents($config->roadRunnerConfigTemplate);
        if ($template === false) {
            throw new ConfigurationException(
                "RoadRunner config is not readable: {$config->roadRunnerConfigTemplate}",
            );
        }

        $rendered = \rtrim($template, "\n")
            . "\n\nendure:\n  grace_period: {$config->gracefulTimeoutMs}ms\n";

        if (\file_put_contents(self::PATH, $rendered) === false) {
            throw new ConfigurationException('Unable to write ' . self::PATH);
        }

        return self::PATH;
    }
}
