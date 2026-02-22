<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Plugin;

/**
 * Abstract base class providing no-op defaults for all plugin methods.
 *
 * Plugin authors can extend this and override only what they need.
 */
abstract class AbstractPlugin implements TemporalPluginInterface
{
    use ClientPluginTrait;
    use ScheduleClientPluginTrait;
    use WorkerPluginTrait;

    public function __construct(
        private readonly string $name,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }
}
