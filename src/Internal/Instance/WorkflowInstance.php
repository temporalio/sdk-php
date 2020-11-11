<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Instance;

use Temporal\Client\Internal\Prototype\WorkflowPrototypeInterface;
use Temporal\Client\Workflow\Meta\SignalMethod;
use Temporal\Client\Workflow\Meta\WorkflowInterface;
use Temporal\Client\Workflow\Meta\WorkflowMethod;
use Temporal\Client\Workflow\Meta\QueryMethod;

/**
 * @psalm-import-type DispatchableHandler from WorkflowInstanceInterface
 */
final class WorkflowInstance extends Instance implements WorkflowInstanceInterface
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
     * @psalm-var array<int, array { 0: QueryMethod, 1: DispatchableHandler }>
     * @see QueryMethod
     *
     * @var array[]
     */
    private array $queryHandlers = [];

    /**
     * @psalm-var array<int, array { 0: SignalMethod, 1: DispatchableHandler }>
     * @see SignalMethod
     *
     * @var array[]
     */
    private array $signalHandlers = [];

    /**
     * @param WorkflowPrototypeInterface $prototype
     * @param object $context
     */
    public function __construct(WorkflowPrototypeInterface $prototype, object $context)
    {
        parent::__construct($prototype, $context);

        foreach ($prototype->getSignalHandlers() as $method => $reflection) {
            $this->queryHandlers[] = [
                self::KEY_META    => $method,
                self::KEY_HANDLER => $this->createHandler($reflection),
            ];
        }

        foreach ($prototype->getQueryHandlers() as $method => $reflection) {
            $this->signalHandlers[] = [
                self::KEY_META    => $method,
                self::KEY_HANDLER => $this->createHandler($reflection),
            ];
        }
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
