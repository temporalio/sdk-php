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
use Temporal\Internal\Marshaller\Type\EnumValueType;
use Temporal\Internal\Support\DateInterval;
use Temporal\Internal\Support\Options;
use Temporal\Nexus\Validation\PrintableAsciiValidator;
use Temporal\Nexus\Validation\ServiceNameValidator;

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
     * Behaviour applied when the caller workflow is cancelled.
     *
     * Defaults to {@see NexusOperationCancellationType::Unspecified}, which
     * the server treats as {@see NexusOperationCancellationType::WaitCompleted}
     * (the sdk-go default).
     *
     * @see NexusOperationCancellationType
     */
    #[Marshal(name: 'cancellationType', type: EnumValueType::class, of: NexusOperationCancellationType::class)]
    public NexusOperationCancellationType $cancellationType;

    public function __construct()
    {
        $this->scheduleToCloseTimeout = \Carbon\CarbonInterval::seconds(0);
        $this->cancellationType = NexusOperationCancellationType::Unspecified;
        parent::__construct();
    }

    /**
     * @param non-empty-string $endpoint
     */
    #[Pure]
    public function withEndpoint(string $endpoint): self
    {
        PrintableAsciiValidator::assert($endpoint, 'Nexus Endpoint');
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
        ServiceNameValidator::assert($service);
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
        \assert(DateInterval::assert($timeout));
        $timeout = DateInterval::parse($timeout, DateInterval::FORMAT_SECONDS);
        \assert($timeout->totalMicroseconds >= 0);

        $self = clone $this;
        $self->scheduleToCloseTimeout = $timeout;
        return $self;
    }

    #[Pure]
    public function withCancellationType(NexusOperationCancellationType|int $type): self
    {
        if (\is_int($type)) {
            $type = NexusOperationCancellationType::from($type);
        }

        $self = clone $this;
        $self->cancellationType = $type;
        return $self;
    }
}
