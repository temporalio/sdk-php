<?php

declare(strict_types=1);

namespace Temporal\Workflow;

use Temporal\Internal\Marshaller\Meta\Marshal;
use Temporal\Internal\Marshaller\Type\DateIntervalType;
use Temporal\Internal\Support\DateInterval;
use Temporal\Internal\Support\Options;

/**
 * Options for executing a Nexus operation from a workflow.
 *
 * @psalm-import-type DateIntervalValue from DateInterval
 */
final class NexusOperationOptions extends Options
{
    /**
     * Name of the Nexus Endpoint as registered in the Temporal server.
     */
    #[Marshal(name: 'endpoint')]
    public string $endpoint = '';

    /**
     * Service name to call. If empty, derived from the service interface.
     */
    #[Marshal(name: 'service')]
    public string $service = '';

    /**
     * Overall timeout for the Nexus operation.
     */
    #[Marshal(name: 'scheduleToCloseTimeout', type: DateIntervalType::class)]
    public \DateInterval $scheduleToCloseTimeout;

    /**
     * @see NexusOperationCancellationType
     * Default UNSPECIFIED → server uses WaitCompleted.
     */
    #[Marshal(name: 'cancellationType')]
    public int $cancellationType = NexusOperationCancellationType::UNSPECIFIED;

    public function __construct()
    {
        $this->scheduleToCloseTimeout = \Carbon\CarbonInterval::seconds(0);
        parent::__construct();
    }

    /**
     * @param non-empty-string $endpoint
     */
    public function withEndpoint(string $endpoint): self
    {
        /** @psalm-suppress TypeDoesNotContainType */
        if ($endpoint === '') {
            throw new \InvalidArgumentException('Nexus endpoint must be a non-empty string');
        }
        $self = clone $this;
        $self->endpoint = $endpoint;
        return $self;
    }

    /**
     * @param non-empty-string $service
     */
    public function withService(string $service): self
    {
        /** @psalm-suppress TypeDoesNotContainType */
        if ($service === '') {
            throw new \InvalidArgumentException('Nexus service must be a non-empty string');
        }
        $self = clone $this;
        $self->service = $service;
        return $self;
    }

    /**
     * @param DateIntervalValue $timeout
     */
    public function withScheduleToCloseTimeout($timeout): self
    {
        $self = clone $this;
        $self->scheduleToCloseTimeout = DateInterval::parse($timeout, DateInterval::FORMAT_SECONDS);
        return $self;
    }

    public function withCancellationType(NexusOperationCancellationType|int $type): self
    {
        $self = clone $this;
        $self->cancellationType = $type instanceof NexusOperationCancellationType ? $type->value : $type;
        return $self;
    }
}
