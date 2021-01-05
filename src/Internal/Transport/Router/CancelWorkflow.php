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
use Temporal\Exception\CancellationException;
use Temporal\Internal\Repository\RepositoryInterface;
use Temporal\Internal\Transport\ClientInterface;
use Temporal\Internal\Workflow\Process\Process;

class CancelWorkflow extends WorkflowProcessAwareRoute
{
    /**
     * @var string
     */
    private const ERROR_PROCESS_NOT_DEFINED = 'Unable to cancel workflow because workflow process #%s was not found';

    /**
     * @var ClientInterface
     */
    private ClientInterface $client;

    /**
     * @param RepositoryInterface $running
     * @param ClientInterface $client
     */
    public function __construct(RepositoryInterface $running, ClientInterface $client)
    {
        $this->client = $client;

        parent::__construct($running);
    }

    /**
     * {@inheritDoc}
     */
    public function handle(array $payload, array $headers, Deferred $resolver): void
    {
        ['runId' => $runId] = $payload;

        $this->cancel($runId);

        $resolver->resolve(['Cancelled' => true]);
    }

    /**
     * @param string $runId
     * @return array
     */
    public function cancel(string $runId): array
    {
        /** @var Process $process */
        $process = $this->running->find($runId);

        if ($process === null) {
            throw new \InvalidArgumentException(\sprintf(self::ERROR_PROCESS_NOT_DEFINED, $runId));
        }

        // todo: need wait?
        $process->cancel();

        return [];
    }
}
