<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\DTO;

use Temporal\Common\IdReusePolicy;
use Temporal\Workflow\ChildWorkflowCancellationType;
use Temporal\Workflow\ChildWorkflowOptions;
use Temporal\Workflow\ParentClosePolicy;

class ChildWorkflowOptionsTestCase extends AbstractDTOMarshalling
{
    /**
     * @throws \ReflectionException
     */
    public function testMarshalling(): void
    {
        $dto = new ChildWorkflowOptions();

        $expected = [
            'Namespace'                => 'default',
            'WorkflowID'               => null,
            'TaskQueueName'            => 'default',
            'WorkflowExecutionTimeout' => 0,
            'WorkflowRunTimeout'       => 0,
            'WorkflowTaskTimeout'      => 0,
            'WaitForCancellation'      => false,
            'WorkflowIDReusePolicy'    => 2,
            'RetryPolicy'              => null,
            'CronSchedule'             => null,
            'ParentClosePolicy'        => 1,
            'Memo'                     => null,
            'SearchAttributes'         => null,
            'StaticDetails'            => '',
            'StaticSummary'            => '',
            'Priority' => [
                'priority_key' => 0,
                'fairness_key' => '',
                'fairness_weight' => 0.0,
            ],
        ];

        $this->assertSame($expected, $this->marshal($dto));
    }

    public function testWorkflowIdReusePolicyChangesNotMutateStateUsingConstant(): void
    {
        $dto = new ChildWorkflowOptions();

        $this->assertNotSame($dto, $dto->withWorkflowIdReusePolicy(
            IdReusePolicy::POLICY_ALLOW_DUPLICATE
        ));
    }

    public function testWorkflowIdReusePolicyChangesNotMutateStateUsingEnum(): void
    {
        $dto = new ChildWorkflowOptions();

        $this->assertNotSame($dto, $dto->withWorkflowIdReusePolicy(
            IdReusePolicy::AllowDuplicateFailedOnly
        ));
        $this->assertSame(IdReusePolicy::AllowDuplicateFailedOnly->value, $dto->workflowIdReusePolicy);
    }

    public function testParentClosePolicyChangesNotMutateStateUsingConstant(): void
    {
        $dto = new ChildWorkflowOptions();

        $this->assertNotSame($dto, $dto->withParentClosePolicy(
            ParentClosePolicy::POLICY_ABANDON
        ));
    }

    public function testParentClosePolicyChangesNotMutateStateUsingEnum(): void
    {
        $dto = new ChildWorkflowOptions();

        $this->assertNotSame($dto, $dto->withParentClosePolicy(
            ParentClosePolicy::Terminate
        ));
        $this->assertSame(ParentClosePolicy::Terminate->value, $dto->parentClosePolicy);
    }

    public function testChildWorkflowCancellationTypeChangesNotMutateStateUsingConstant(): void
    {
        $dto = new ChildWorkflowOptions();

        $this->assertNotSame($dto, $dto->withChildWorkflowCancellationType(
            ChildWorkflowCancellationType::WAIT_CANCELLATION_COMPLETED
        ));
    }

    public function testChildWorkflowCancellationTypeChangesNotMutateStateUsingEnum(): void
    {
        $dto = new ChildWorkflowOptions();

        $this->assertNotSame($dto, $dto->withChildWorkflowCancellationType(
            ChildWorkflowCancellationType::WaitCancellationCompleted
        ));
        $this->assertSame(ChildWorkflowCancellationType::TryCancel->value, $dto->cancellationType);
    }
}
