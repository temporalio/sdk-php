<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Worker;

use Temporal\Client\Meta\Factory as ReaderFactory;
use Temporal\Client\Meta\ReaderInterface;
use Temporal\Client\Workflow\WorkflowTransportInterface;
use Temporal\Client\Workflow\WorkflowWorker;
use Temporal\Client\Workflow\WorkflowWorkerInterface;

final class Factory
{
    /**
     * @var ReaderInterface|null
     */
    private ?ReaderInterface $reader = null;

    /**
     * @return ReaderInterface
     */
    private function createReader(): ReaderInterface
    {
        return (new ReaderFactory())->create();
    }

    /**
     * @param ReaderInterface $reader
     * @return $this
     */
    public function withReader(ReaderInterface $reader): self
    {
        $self = clone $this;
        $self->reader = $reader;

        return $self;
    }

    /**
     * @param WorkflowTransportInterface $transport
     * @return WorkflowWorkerInterface
     * @throws \Exception
     */
    public function forWorkflows(WorkflowTransportInterface $transport): WorkflowWorkerInterface
    {
        $reader = $this->reader ?? $this->createReader();

        return new WorkflowWorker($reader, $transport);
    }
}
