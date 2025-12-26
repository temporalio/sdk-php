<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Environment;

use Temporal\Worker\Transport\Command\Server\TickInfo;

class Environment implements EnvironmentInterface
{
    protected \DateTimeInterface $tickTime;
    protected bool $isReplaying = false;

    public function __construct()
    {
        $this->tickTime = new \DateTimeImmutable('now');
    }

    public function now(): \DateTimeInterface
    {
        return $this->tickTime;
    }

    public function isReplaying(): bool
    {
        return $this->isReplaying;
    }

    public function update(TickInfo $info): void
    {
        $this->isReplaying = $info->isReplaying;
        $this->tickTime = $info->time;
    }
}
