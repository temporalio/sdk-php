<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Transport\Router;

use React\Promise\Deferred;
use Temporal\Client\Internal\Repository\RepositoryInterface;
use Temporal\Client\Internal\Transport\CapturedClientInterface;
use Temporal\Client\Workflow;

class DestroyWorkflow extends WorkflowProcessAwareRoute
{
    /**
     * @var string
     */
    private const ERROR_PROCESS_NOT_DEFINED = 'Unable to kill workflow because workflow process #%s was not found';

    /**
     * @var string
     */
    private const ERROR_RID_NOT_DEFINED =
        'Killing a workflow requires the id (rid argument) ' .
        'of the running workflow process';

    /**
     * @var CapturedClientInterface
     */
    private CapturedClientInterface $client;

    /**
     * @param RepositoryInterface $running
     * @param CapturedClientInterface $client
     */
    public function __construct(RepositoryInterface $running, CapturedClientInterface $client)
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

        $process = $this->findProcessOrFail($runId);

        $requests = $this->kill($runId);

        $info = $process->getContext()->getInfo();

        $resolver->resolve([
            'WorkflowExecution' => $info->execution,
            'CancelRequests'    => $requests,
        ]);
    }

    /**
     * @param string $runId
     * @return array
     */
    public function kill(string $runId): array
    {
        $process = $this->running->find($runId);

        if ($process === null) {
            throw new \InvalidArgumentException(\sprintf(self::ERROR_PROCESS_NOT_DEFINED, $runId));
        }

        Workflow::setCurrentContext(null);
        $this->running->remove($runId);

        $result = [];

        foreach ($this->client->fetchUnresolvedRequests() as $id => $promise) {
            $result[] = $id;
            $promise->cancel();
        }

        return $result;
    }
}
