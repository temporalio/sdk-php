<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration\Prototype;

use Temporal\Activity\ActivityInterface;
use Temporal\Activity\LocalActivityInterface;
use Temporal\Common\MethodRetry;
use Temporal\Internal\Declaration\ActivityInstance;
use Temporal\Internal\Declaration\EntityNameValidator;

final class ActivityPrototype extends Prototype
{
    private ?MethodRetry $methodRetry = null;
    private ?ActivityInstance $instance = null;
    private ?\Closure $factory = null;
    private bool $isLocalActivity;

    /**
     * @param non-empty-string $name
     */
    public function __construct(
        ActivityInterface $interface,
        string $name,
        \ReflectionMethod $handler,
        \ReflectionClass $class,
    ) {
        EntityNameValidator::validateActivity($name);
        $this->isLocalActivity = $interface instanceof LocalActivityInterface;

        parent::__construct($name, $handler, $class);
    }

    public function getHandler(): \ReflectionMethod
    {
        $handler = parent::getHandler();
        \assert($handler !== null);

        return $handler;
    }

    public function getMethodRetry(): ?MethodRetry
    {
        return $this->methodRetry;
    }

    public function setMethodRetry(?MethodRetry $attribute): void
    {
        $this->methodRetry = $attribute;
    }

    public function getInstance(): ActivityInstance
    {
        if ($this->instance !== null) {
            return $this->instance;
        }

        if ($this->factory !== null) {
            $instance = \call_user_func($this->factory, $this->getClass());
            return new ActivityInstance($this, $instance);
        }

        return new ActivityInstance($this, $this->getClass()->newInstance());
    }

    /**
     * @return $this
     */
    public function withInstance(object $instance): self
    {
        $proto = clone $this;
        $proto->instance = new ActivityInstance($proto, $instance);

        return $proto;
    }

    public function withFactory(\Closure $factory): self
    {
        $proto = clone $this;
        $proto->factory = $factory;

        return $proto;
    }

    public function isLocalActivity(): bool
    {
        return $this->isLocalActivity;
    }

    public function getFactory(): ?\Closure
    {
        return $this->factory;
    }
}
