<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\DTO;

use Carbon\CarbonInterval;
use Temporal\Api\Common\V1\SearchAttributes;
use Temporal\Client\WorkflowOptions;
use Temporal\Common\IdReusePolicy;
use Temporal\Common\RetryOptions;
use Temporal\Common\TypedSearchAttributes;
use Temporal\Common\Uuid;
use Temporal\Common\WorkflowIdConflictPolicy;
use Temporal\DataConverter\DataConverter;

class WorkflowOptionsTestCase extends AbstractDTOMarshalling
{
    /**
     * @throws \ReflectionException
     */
    public function testMarshalling(): void
    {
        $dto = new WorkflowOptions();
        $expected = [
            'WorkflowID'               => $dto->workflowId,
            'TaskQueue'                => 'default',
            'EnableEagerStart'         => false,
            'WorkflowExecutionTimeout' => 0,
            'WorkflowRunTimeout'       => 0,
            'WorkflowStartDelay'       => 0,
            'WorkflowTaskTimeout'      => 0,
            'WorkflowIDReusePolicy'    => 2,
            'WorkflowIdConflictPolicy' => [
                'name' => 'Unspecified',
                'value' => 0,
            ],
            'RetryPolicy'              => null,
            'CronSchedule'             => null,
            'Memo'                     => null,
            'SearchAttributes'         => null,
            'StaticDetails'            => '',
            'StaticSummary'            => '',
            'Priority' => [
                'priority_key' => 0,
            ],
        ];

        $result = $this->marshal($dto);
        unset($result['typedSearchAttributes']);

        $this->assertSame($expected, $result);
    }

    public function testWorkflowIdChangesNotMutateState(): void
    {
        $dto = new WorkflowOptions();

        $this->assertNotSame($dto, $dto->withWorkflowId(Uuid::v4()));
    }

    public function testTaskQueueChangesNotMutateState(): void
    {
        $dto = new WorkflowOptions();

        $this->assertNotSame($dto, $dto->withTaskQueue(Uuid::v4()));
    }

    public function testEagerStateNotMutateState(): void
    {
        $dto = new WorkflowOptions();

        $this->assertNotSame($dto, $newDto = $dto->withEagerStart());
        $this->assertFalse($dto->eagerStart);
        $this->assertTrue($newDto->eagerStart);
    }

    public function testWorkflowExecutionTimeoutChangesNotMutateState(): void
    {
        $dto = new WorkflowOptions();

        $this->assertNotSame($dto, $dto->withWorkflowExecutionTimeout(
            CarbonInterval::days(42)
        ));
    }

    public function testWorkflowRunTimeoutChangesNotMutateState(): void
    {
        $dto = new WorkflowOptions();

        $this->assertNotSame($dto, $dto->withWorkflowRunTimeout(
            CarbonInterval::days(42)
        ));
    }

    public function testWorkflowTaskTimeoutChangesNotMutateState(): void
    {
        $dto = new WorkflowOptions();

        $this->assertNotSame($dto, $dto->withWorkflowTaskTimeout(
            CarbonInterval::seconds(10)
        ));
    }

    public function testWorkflowStartDelayChangesNotMutateState(): void
    {
        $dto = new WorkflowOptions();

        $this->assertNotSame($dto, $dto->withWorkflowStartDelay(
            CarbonInterval::seconds(10)
        ));
    }

    public function testWorkflowIdReusePolicyChangesNotMutateStateUsingConstant(): void
    {
        $dto = new WorkflowOptions();

        $this->assertNotSame($dto, $dto->withWorkflowIdReusePolicy(
            IdReusePolicy::POLICY_ALLOW_DUPLICATE
        ));
    }

    public function testWorkflowIdReusePolicyChangesNotMutateStateUsingEnum(): void
    {
        $dto = new WorkflowOptions();

        $this->assertNotSame($dto, $dto->withWorkflowIdReusePolicy(
            IdReusePolicy::AllowDuplicateFailedOnly
        ));
        $this->assertSame(IdReusePolicy::AllowDuplicateFailedOnly->value, $dto->workflowIdReusePolicy);
    }

    public function testWorkflowIdConflictPolicy(): void
    {
        $dto = new WorkflowOptions();

        $this->assertNotSame($dto, $newDto = $dto->withWorkflowIdConflictPolicy(
            WorkflowIdConflictPolicy::Fail
        ));
        $this->assertSame(WorkflowIdConflictPolicy::Unspecified, $dto->workflowIdConflictPolicy);
        $this->assertSame(WorkflowIdConflictPolicy::Fail, $newDto->workflowIdConflictPolicy);
    }

    public function testRetryOptionsChangesNotMutateState(): void
    {
        $dto = new WorkflowOptions();

        $this->assertNotSame($dto, $dto->withRetryOptions(
            RetryOptions::new()
        ));
    }

    public function testCronScheduleChangesNotMutateState(): void
    {
        $dto = new WorkflowOptions();

        $this->assertNotSame($dto, $dto->withCronSchedule('* * * * *'));
    }

    public function testMemoChangesNotMutateState(): void
    {
        $dto = new WorkflowOptions();

        $this->assertNotSame($dto, $dto->withMemo([1, 2, 3]));
    }

    public function testSearchAttributesChangesNotMutateState(): void
    {
        $dto = new WorkflowOptions();

        $this->assertNotSame($dto, $dto->withSearchAttributes([1, 2, 3]));
    }

    public function testEmptyMemoCasting(): void
    {
        $dto = new WorkflowOptions();
        $this->assertNull($dto->toMemo(DataConverter::createDefault()));
    }

    public function testDoNotSetEmptyMemo(): void
    {
        $dto = WorkflowOptions::new()
            ->withMemo([]);

        $this->assertNull($dto->toMemo(DataConverter::createDefault()));
    }

    public function testCantSetTypedSAIfUntypedExist(): void
    {
        $dto = WorkflowOptions::new()->withSearchAttributes([]);

        $this->expectException(\LogicException::class);

        $dto->withTypedSearchAttributes(TypedSearchAttributes::empty());
    }

    public function testCantSetUntypedSAIfTypedExist(): void
    {
        $dto = WorkflowOptions::new()->withTypedSearchAttributes(TypedSearchAttributes::empty());

        $this->expectException(\LogicException::class);

        $dto->withSearchAttributes([]);
    }

    public function testEmptySearchAttributesCasting(): void
    {
        $dto = new WorkflowOptions();
        $this->assertNull($dto->toSearchAttributes(
            DataConverter::createDefault()
        ));
    }

    public function testSetUntypedSearchAttributesCasting(): void
    {
        $dto = WorkflowOptions::new()
            ->withSearchAttributes(['foo' => 'bar']);

        $result = $dto->toSearchAttributes(DataConverter::createDefault());

        $this->assertInstanceOf(SearchAttributes::class, $result);
        $this->assertCount(1, $result->getIndexedFields());
    }

    public function testSetTypedSearchAttributesCasting(): void
    {
        $dto = WorkflowOptions::new()
            ->withTypedSearchAttributes(TypedSearchAttributes::empty()->withUntypedValue('foo', 'bar'));

        $result = $dto->toSearchAttributes(DataConverter::createDefault());

        $this->assertInstanceOf(SearchAttributes::class, $result);
        $this->assertCount(1, $result->getIndexedFields());
    }
}
