<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\DataConverter;

use PHPUnit\Framework\TestCase;
use Temporal\Activity\ActivityInfo;
use Temporal\DataConverter\ActivitySerializationContext;
use Temporal\Workflow\WorkflowExecution;

final class ActivitySerializationContextTestCase extends TestCase
{
    public function testFromWorkflowActivityInfo(): void
    {
        $info = new ActivityInfo();
        $info->workflowNamespace = 'workflow-namespace';
        $info->namespace = 'workflow-namespace';
        $info->workflowExecution = new WorkflowExecution('workflow-id', 'workflow-run-id');
        $info->type->name = 'MyActivity';
        $info->taskQueue = 'queue';

        $context = ActivitySerializationContext::fromActivityInfo($info, true);

        self::assertSame('workflow-namespace', $context->namespace);
        self::assertSame('workflow-id', $context->workflowId);
        self::assertSame('MyActivity', $context->activityType);
        self::assertSame('queue', $context->taskQueue);
        self::assertTrue($context->isLocal);
    }

    public function testFromStandaloneActivityInfo(): void
    {
        $info = new ActivityInfo();
        $info->workflowNamespace = '';
        $info->namespace = 'activity-namespace';
        $info->activityRunId = 'activity-run-id';
        $info->workflowExecution = new WorkflowExecution('', '');
        $info->type->name = 'MyActivity';
        $info->taskQueue = 'queue';

        $context = ActivitySerializationContext::fromActivityInfo($info, false);

        self::assertSame('activity-namespace', $context->namespace);
        self::assertNull($context->workflowId);
        self::assertNull($context->workflowType);
        self::assertSame('MyActivity', $context->activityType);
        self::assertSame('queue', $context->taskQueue);
    }
}
