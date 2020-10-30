<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Workflow;

use Temporal\Client\Protocol\ClientInterface;
use Temporal\Client\Workflow;

final class RunningWorkflows
{
    /**
     * @var string
     */
    private const ERROR_PROCESS_NOT_DEFINED = 'Unable to kill workflow because workflow process #%s was not found';

    /**
     * @var array
     */
    private array $processes = [];

    /**
     * @param WorkflowContextInterface $context
     * @param WorkflowDeclarationInterface $declaration
     * @return Process
     */
    public function run(WorkflowContextInterface $context, WorkflowDeclarationInterface $declaration): Process
    {
        $info = $context->getInfo();

        return $this->processes[$info->processId] = new Process($context, $declaration);
    }

    /**
     * @param string $runId
     * @return Process|null
     */
    public function find(string $runId): ?Process
    {
        return $this->processes[$runId] ?? null;
    }

    /**
     * @param string $runId
     * @param ClientInterface $client
     * @return array
     */
    public function kill(string $runId, ClientInterface $client): array
    {
        $process = $this->find($runId);

        if ($process === null) {
            throw new \InvalidArgumentException(\sprintf(self::ERROR_PROCESS_NOT_DEFINED, $runId));
        }

        Workflow::setCurrentContext(null);
        unset($this->processes[$runId]);
        $context = $process->getContext();

        $requests = $context->getSendRequestIdentifiers();
        foreach ($requests as $id) {
            $client->cancel($id);
        }
        return $requests;
    }
}
