<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Declaration;

abstract class HandledDeclaration extends Declaration implements HandledDeclarationInterface
{
    /**
     * @var object
     */
    protected object $method;

    /**
     * @var \ReflectionFunctionAbstract
     */
    protected \ReflectionFunctionAbstract $handler;

    /**
     * @param object $ctx
     * @param object $meta
     * @param object $method
     * @param \ReflectionFunctionAbstract $handler
     */
    public function __construct(object $meta, object $method, \ReflectionFunctionAbstract $handler)
    {
        $this->method = $method;
        $this->handler = $handler;

        parent::__construct($meta);
    }

    /**
     * {@inheritDoc}
     */
    public function getMethod(): object
    {
        return $this->method;
    }

    /**
     * {@inheritDoc}
     */
    public function getHandler(): \ReflectionFunctionAbstract
    {
        return $this->handler;
    }
}
