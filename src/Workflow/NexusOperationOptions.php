<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Workflow;

use JetBrains\PhpStorm\Pure;
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
     */
    #[Marshal(name: 'cancellationType')]
    public int $cancellationType;

    public function __construct()
    {
        $this->scheduleToCloseTimeout = \Carbon\CarbonInterval::seconds(0);
        $this->cancellationType = NexusOperationCancellationType::Unspecified->value;
        parent::__construct();
    }

    /**
     * @param non-empty-string $endpoint
     */
    #[Pure]
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
    #[Pure]
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
    #[Pure]
    public function withScheduleToCloseTimeout($timeout): self
    {
        $self = clone $this;
        $self->scheduleToCloseTimeout = DateInterval::parse($timeout, DateInterval::FORMAT_SECONDS);
        return $self;
    }

    #[Pure]
    public function withCancellationType(NexusOperationCancellationType|int $type): self
    {
        \is_int($type) and $type = NexusOperationCancellationType::from($type);

        $self = clone $this;
        $self->cancellationType = $type->value;
        return $self;
    }
}
