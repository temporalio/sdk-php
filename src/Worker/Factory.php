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
use Temporal\Client\Transport\SyncTransportInterface;
use Temporal\Client\Transport\TransportInterface;

final class Factory
{
    /**
     * @var TransportInterface
     */
    private TransportInterface $transport;

    /**
     * @var ReaderInterface|null
     */
    private ?ReaderInterface $reader = null;

    /**
     * @param TransportInterface $transport
     */
    public function __construct(TransportInterface $transport)
    {
        $this->transport = $transport;
    }

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
     * @param TransportInterface $transport
     * @return $this
     */
    public function over(TransportInterface $transport): self
    {
        $self = clone $this;
        $self->transport = $transport;

        return $self;
    }

    /**
     * @param TransportInterface $transport
     * @return static
     */
    public static function create(TransportInterface $transport): self
    {
        return new self($transport);
    }

    /**
     * @param iterable|array $workflows
     * @return WorkflowWorkerInterface
     * @throws \Exception
     */
    public function forWorkflows(iterable $workflows = []): WorkflowWorkerInterface
    {
        $reader = $this->reader ?? $this->createReader();

        return new WorkflowWorker($reader, $this->transport, $workflows);
    }
}
