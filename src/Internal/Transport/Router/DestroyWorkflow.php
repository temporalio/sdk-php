<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Transport\Router;

use React\Promise\Deferred;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\DestructMemorizedInstanceException;
use Temporal\Internal\Support\GarbageCollector;
use Temporal\Internal\Workflow\Process\Process;
use Temporal\Internal\Workflow\ProcessCollection;
use Temporal\Worker\LoopInterface;
use Temporal\Worker\Transport\Command\ServerRequestInterface;

class DestroyWorkflow extends WorkflowProcessAwareRoute
{
    /** Maximum number of ticks before GC call. */
    private const GC_THRESHOLD = 1000;
    /** Interval between GC calls in seconds. */
    private const GC_TIMEOUT_SECONDS = 30;

    private GarbageCollector $gc;

    public function __construct(
        ProcessCollection $running,
        protected LoopInterface $loop
    ) {
        $this->gc = new GarbageCollector(self::GC_THRESHOLD, self::GC_TIMEOUT_SECONDS);
        parent::__construct($running);
    }

    /**
     * {@inheritDoc}
     */
    public function handle(ServerRequestInterface $request, array $headers, Deferred $resolver): void
    {
        $this->kill($request->getID());

        $resolver->resolve(EncodedValues::fromValues([null]));
    }

    /**
     * @param string $runId
     * @return array
     */
    public function kill(string $runId): array
    {
        /** @var Process $process */
        $process = $this->running
            ->pull($runId, "Unable to kill workflow because workflow process #$runId was not found");

        $process->cancel(new DestructMemorizedInstanceException());
        $this->loop->once(
            LoopInterface::ON_FINALLY,
            function () use ($process) {
                $process->destroy();

                // Collect garbage if needed
                if ($this->gc->check()) {
                    $this->gc->collect();
                }
            },
        );

        return [];
    }
}
