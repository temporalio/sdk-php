<?php

declare(strict_types=1);

namespace Temporal\Activity;

use Temporal\Common\MethodRetry;

/**
 * A marker interface for ActivityOptions and LocalActivityOptions
 */
interface ActivityOptionsInterface
{
    /**
     * @return $this
     */
    public function mergeWith(?MethodRetry $retry = null): self;
}
