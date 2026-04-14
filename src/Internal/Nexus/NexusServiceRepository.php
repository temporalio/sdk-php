<?php

declare(strict_types=1);

namespace Temporal\Internal\Nexus;

use Nexus\Sdk\Handler\ServiceImplInstance;

/**
 * Stores registered Nexus service implementations.
 */
final class NexusServiceRepository
{
    /** @var ServiceImplInstance[] */
    private array $instances = [];

    public function add(ServiceImplInstance $instance): void
    {
        $this->instances[] = $instance;
    }

    /**
     * @return ServiceImplInstance[]
     */
    public function getInstances(): array
    {
        return $this->instances;
    }
}
