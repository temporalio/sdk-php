<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Declaration;

abstract class HandledDeclaration extends Declaration implements HandledDeclarationInterface
{
    /**
     * @var \Closure
     */
    private \Closure $handler;

    /**
     * @var \ReflectionFunctionAbstract|null
     */
    private ?\ReflectionFunctionAbstract $reflection = null;

    /**
     * @var int
     */
    private int $mode = self::MODE_AUTO;

    /**
     * @param string $name
     * @param callable $handler
     */
    public function __construct(string $name, callable $handler)
    {
        $this->handler = \Closure::fromCallable($handler);

        parent::__construct($name);
    }

    /**
     * @return int
     */
    public function getHandlerMode(): int
    {
        return $this->mode;
    }

    /**
     * {@inheritDoc}
     */
    public function getHandler(): callable
    {
        return $this->handler;
    }

    /**
     * {@inheritDoc}
     */
    public function getReflectionHandler(): \ReflectionFunctionAbstract
    {
        if ($this->reflection === null) {
            $this->reflection = new \ReflectionFunction(\Closure::fromCallable($this->handler));
        }

        return $this->reflection;
    }
}
