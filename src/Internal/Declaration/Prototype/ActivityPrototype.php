<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration\Prototype;

use Temporal\Common\MethodRetry;
use Temporal\Internal\Declaration\ActivityInstance;

final class ActivityPrototype extends Prototype
{
    /**
     * @var MethodRetry|null
     */
    private ?MethodRetry $methodRetry = null;

    /**
     * @var ActivityInstance|null
     */
    private ?ActivityInstance $instance = null;

    /**
     * @return MethodRetry|null
     */
    public function getMethodRetry(): ?MethodRetry
    {
        return $this->methodRetry;
    }

    /**
     * @param MethodRetry|null $attribute
     */
    public function setMethodRetry(?MethodRetry $attribute): void
    {
        $this->methodRetry = $attribute;
    }

    /**
     * @return ?ActivityInstance
     */
    public function getInstance(): ?ActivityInstance
    {
        return $this->instance;
    }

    /**
     * @param object $instance
     * @return $this
     */
    public function withInstance(object $instance): self
    {
        $proto = clone $this;
        $proto->instance = new ActivityInstance($proto, $instance);

        return $proto;
    }
}
