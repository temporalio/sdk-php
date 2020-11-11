<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Declaration\Prototype;

final class WorkflowPrototype extends Prototype
{
    /**
     * @var array<string, \ReflectionFunctionAbstract>
     */
    private array $queryHandlers = [];

    /**
     * @var array<string, \ReflectionFunctionAbstract>
     */
    private array $signalHandlers = [];

    /**
     * @param string $name
     * @param \ReflectionFunctionAbstract $fun
     */
    public function addQueryHandler(string $name, \ReflectionFunctionAbstract $fun): void
    {
        $this->queryHandlers[$name] = $fun;
    }

    /**
     * @return iterable<string, \ReflectionFunctionAbstract>
     */
    public function getQueryHandlers(): iterable
    {
        return $this->queryHandlers;
    }

    /**
     * @param string $name
     * @param \ReflectionFunctionAbstract $fun
     */
    public function addSignalHandler(string $name, \ReflectionFunctionAbstract $fun): void
    {
        $this->signalHandlers[$name] = $fun;
    }

    /**
     * @return iterable<string, \ReflectionFunctionAbstract>
     */
    public function getSignalHandlers(): iterable
    {
        return $this->signalHandlers;
    }
}
