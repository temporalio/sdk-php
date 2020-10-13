<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Worker;

use Temporal\Client\Declaration\WorkflowInterface;
use Temporal\Client\Meta\ReaderInterface;
use Temporal\Client\Protocol\WorkflowProtocol;
use Temporal\Client\Protocol\WorkflowProtocolInterface;
use Temporal\Client\Transport\TransportInterface;

class WorkflowWorker extends Worker implements WorkflowWorkerInterface
{
    use WorkflowProviderTrait;

    /**
     * @var WorkflowProtocolInterface
     */
    private WorkflowProtocolInterface $protocol;

    /**
     * @param ReaderInterface $reader
     * @param TransportInterface $transport
     * @param iterable|WorkflowInterface[] $workflows
     */
    public function __construct(ReaderInterface $reader, TransportInterface $transport, iterable $workflows)
    {
        $this->bootWorkflowProviderTrait();

        parent::__construct($reader, $transport);

        $this->protocol = new WorkflowProtocol();

        foreach ($workflows as $workflow) {
            $this->addWorkflow($workflow);
        }
    }

    /**
     * @param string $name
     * @return int
     * @throws \Throwable
     */
    public function run(string $name = self::DEFAULT_WORKER_ID): int
    {
        try {
            while ($request = $this->transport->waitForMessage()) {
                $this->transport->send(
                    $this->protocol->next($request)
                );
            }
        } catch (\Throwable $e) {
            $this->throw($e);

            return $e->getCode() ?: 1;
        }

        return 0;
    }
}
