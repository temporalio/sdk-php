<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Fixtures\ServiceHandler;

use Temporal\Client\WorkflowOptions;
use Temporal\Nexus\Attribute\OperationCancel;
use Temporal\Nexus\Handler\OperationCancelDetails;
use Temporal\Nexus\Handler\OperationContext;
use Temporal\Nexus\WorkflowHandle;
use Temporal\Tests\Nexus\Fixtures\Service\CancelSignaturesServiceInterface;

final class CancelSignaturesService implements CancelSignaturesServiceInterface
{
    /** @var array<string, mixed> */
    public array $cancelCalls = [];

    public function legacy(string $name): WorkflowHandle
    {
        return WorkflowHandle::fromWorkflowMethod(
            self::class,
            WorkflowOptions::new()->withWorkflowId($name),
        );
    }

    public function contextAndDetails(string $name): WorkflowHandle
    {
        return WorkflowHandle::fromWorkflowMethod(
            self::class,
            WorkflowOptions::new()->withWorkflowId($name),
        );
    }

    public function reversed(string $name): WorkflowHandle
    {
        return WorkflowHandle::fromWorkflowMethod(
            self::class,
            WorkflowOptions::new()->withWorkflowId($name),
        );
    }

    public function noArgs(string $name): WorkflowHandle
    {
        return WorkflowHandle::fromWorkflowMethod(
            self::class,
            WorkflowOptions::new()->withWorkflowId($name),
        );
    }

    #[OperationCancel(operation: 'legacy')]
    public function cancelLegacy(string $token): void
    {
        $this->cancelCalls['legacy'] = $token;
    }

    #[OperationCancel(operation: 'contextAndDetails')]
    public function cancelContextAndDetails(OperationContext $context, OperationCancelDetails $details): void
    {
        $this->cancelCalls['contextAndDetails'] = [$context, $details];
    }

    #[OperationCancel(operation: 'reversed')]
    public function cancelReversed(OperationCancelDetails $details, OperationContext $context): void
    {
        $this->cancelCalls['reversed'] = [$details, $context];
    }

    #[OperationCancel(operation: 'noArgs')]
    public function cancelNoArgs(): void
    {
        $this->cancelCalls['noArgs'] = true;
    }
}
