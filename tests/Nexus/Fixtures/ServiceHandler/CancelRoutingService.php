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
use Temporal\Nexus\WorkflowHandle;
use Temporal\Tests\Nexus\Fixtures\Service\CancelRoutingServiceInterface;

final class CancelRoutingService implements CancelRoutingServiceInterface
{
    public bool $explicitCancelCalled = false;

    public function autoCancel(string $name): WorkflowHandle
    {
        return WorkflowHandle::fromWorkflowMethod(
            self::class,
            WorkflowOptions::new()->withWorkflowId($name),
        );
    }

    public function explicitOverride(string $name): WorkflowHandle
    {
        return WorkflowHandle::fromWorkflowMethod(
            self::class,
            WorkflowOptions::new()->withWorkflowId($name),
        );
    }

    #[OperationCancel(operation: 'explicitOverride')]
    public function cancelExplicitOverride(string $token): void
    {
        $this->explicitCancelCalled = true;
    }
}
