<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Prototype;

use Temporal\Client\Workflow\Meta\QueryMethod;
use Temporal\Client\Workflow\Meta\SignalMethod;
use Temporal\Client\Workflow\Meta\WorkflowInterface;
use Temporal\Client\Workflow\Meta\WorkflowMethod;

final class WorkflowPrototype extends Prototype implements WorkflowPrototypeInterface
{
    /**
     * @var int
     */
    private const KEY_META = 0x00;

    /**
     * @var int
     */
    private const KEY_HANDLER = 0x01;

    /**
     * @psalm-var array<int, array { 0: QueryMethod, 1: \ReflectionFunctionAbstract }>
     *
     * @var array[]
     */
    private array $queryHandlers = [];

    /**
     * @psalm-var array<int, array { 0: SignalMethod, 1: \ReflectionFunctionAbstract }>
     *
     * @var array[]
     */
    private array $signalHandlers = [];

    /**
     * @param WorkflowInterface $meta
     * @param WorkflowMethod $method
     * @param \ReflectionFunctionAbstract $handler
     */
    public function __construct(WorkflowInterface $meta, WorkflowMethod $method, \ReflectionFunctionAbstract $handler)
    {
        parent::__construct($meta, $method, $handler);
    }

    /**
     * {@inheritDoc}
     */
    public function getMetadata(): WorkflowInterface
    {
        $result = parent::getMetadata();

        assert($result instanceof WorkflowInterface, 'Postcondition failed');

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function getMethod(): WorkflowMethod
    {
        $result = parent::getMethod();

        assert($result instanceof WorkflowMethod, 'Postcondition failed');

        return $result;
    }

    /**
     * @param QueryMethod $meta
     * @param \ReflectionFunctionAbstract $fun
     */
    public function addQueryHandler(QueryMethod $meta, \ReflectionFunctionAbstract $fun): void
    {
        $this->queryHandlers[] = [
            self::KEY_META    => $meta,
            self::KEY_HANDLER => $fun,
        ];
    }

    /**
     * @param SignalMethod $meta
     * @param \ReflectionFunctionAbstract $fun
     */
    public function addSignalHandler(SignalMethod $meta, \ReflectionFunctionAbstract $fun): void
    {
        $this->signalHandlers[] = [
            self::KEY_META    => $meta,
            self::KEY_HANDLER => $fun,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getQueryHandlers(): iterable
    {
        foreach ($this->queryHandlers as [self::KEY_META => $meta, self::KEY_HANDLER => $handler]) {
            yield $meta => $handler;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getSignalHandlers(): iterable
    {
        foreach ($this->signalHandlers as [self::KEY_META => $meta, self::KEY_HANDLER => $handler]) {
            yield $meta => $handler;
        }
    }
}
