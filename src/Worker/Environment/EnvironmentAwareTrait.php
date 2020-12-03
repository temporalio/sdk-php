<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Worker\Environment;

use Carbon\CarbonInterface;
use Carbon\CarbonTimeZone;

/**
 * @mixin EnvironmentInterface
 */
trait EnvironmentAwareTrait
{
    /**
     * @var EnvironmentInterface
     */
    protected EnvironmentInterface $env;

    /**
     * @param EnvironmentInterface $env
     */
    protected function setEnvironment(EnvironmentInterface $env)
    {
        $this->env = $env;
    }

    /**
     * {@inheritDoc}
     */
    public function getTimeZone(): CarbonTimeZone
    {
        return $this->env->getTimeZone();
    }

    /**
     * {@inheritDoc}
     */
    public function now(): CarbonInterface
    {
        return $this->env->now();
    }

    /**
     * {@inheritDoc}
     */
    public function getRunId(): ?string
    {
        return $this->env->getRunId();
    }

    /**
     * {@inheritDoc}
     */
    public function isReplaying(): bool
    {
        return $this->env->isReplaying();
    }
}
