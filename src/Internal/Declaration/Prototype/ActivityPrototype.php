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

final class ActivityPrototype extends Prototype
{
    /**
     * @var MethodRetry|null
     */
    private ?MethodRetry $methodRetry = null;

    /**
     * @var object|null
     */
    private ?object $instance = null;

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
     * @return object|null
     */
    public function getInstance(): ?object
    {
        return $this->instance;
    }

    /**
     * @param object|null $instance
     */
    public function setInstance(?object $instance): void
    {
        $this->instance = $instance;
    }
}
