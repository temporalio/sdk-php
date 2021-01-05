<?php


namespace Temporal\Internal\Workflow\Process;


use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Temporal\Internal\Queue\QueueInterface;
use Temporal\Internal\ServiceContainer;
use Temporal\Worker\LoopInterface;

class CancellableScope implements PromiseInterface
{
    /** @var CancellableScope[] */
    private array $child = [];

    /**
     * @var CancellableScope
     */
    private CancellableScope $parent;

    /**
     * @var Deferred
     */
    private Deferred $deferred;

    /**
     * @var QueueInterface
     */
    #[Immutable]
    public QueueInterface $queue;

    /**
     * @var LoopInterface
     */
    #[Immutable]
    public LoopInterface $loop;

    public function __construct(
        ServiceContainer $services,
        CancellableScope $parent = null,
        callable $handler,
        array $args = []
    ) {

    }

    /**
     * {@inheritDoc}
     */
    public function promise(): PromiseInterface
    {
        return $this->deferred->promise();
    }

    public function newScope(): self
    {
    }

    public function newDetachedScope(): self
    {
    }

    public function push(PromiseInterface $promise): PromiseInterface
    {
    }

    public function isCancelled(): bool
    {
    }

    public function cancel()
    {
    }


}
