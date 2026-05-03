<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Nexus;

use Temporal\Nexus\Handler\OperationCancelDetails;
use Temporal\Nexus\Handler\OperationContext;
use Temporal\Nexus\Handler\OperationStartDetails;
use Temporal\Nexus\NexusOperationContext;

/**
 * Composite Nexus dispatch state held in the {@see \Temporal\Nexus\Nexus} facade slot.
 *
 * Outer scope (per RoadRunner task) carries {@see $operation}; inner scope
 * (per start/cancel dispatch) layers in {@see $current}, {@see $startDetails},
 * {@see $cancelDetails} via the `with*` helpers.
 *
 * @internal
 */
final class NexusContext
{
    public function __construct(
        public readonly ?NexusOperationContext $operation = null,
        public readonly ?OperationContext $current = null,
        public readonly ?OperationStartDetails $startDetails = null,
        public readonly ?OperationCancelDetails $cancelDetails = null,
    ) {}

    public function withOperation(?NexusOperationContext $operation): self
    {
        return new self($operation, $this->current, $this->startDetails, $this->cancelDetails);
    }

    public function withCurrent(?OperationContext $current): self
    {
        return new self($this->operation, $current, $this->startDetails, $this->cancelDetails);
    }

    public function withStartDetails(?OperationStartDetails $details): self
    {
        return new self($this->operation, $this->current, $details, $this->cancelDetails);
    }

    public function withCancelDetails(?OperationCancelDetails $details): self
    {
        return new self($this->operation, $this->current, $this->startDetails, $details);
    }
}
