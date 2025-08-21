<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\DTO;

use Temporal\Common\Versioning\VersioningBehavior;
use Temporal\Common\Versioning\WorkerDeploymentVersion;
use Temporal\Worker\WorkerDeploymentOptions;
use Temporal\Worker\WorkerOptions;
use Temporal\Worker\WorkflowPanicPolicy;

class WorkerOptionsTestCase extends AbstractDTOMarshalling
{
    /**
     * @throws \ReflectionException
     */
    public function testMarshalling(): void
    {
        $dto = new WorkerOptions();

        $expected = [
            'MaxConcurrentActivityExecutionSize' => 0,
            'WorkerActivitiesPerSecond' => 0.0,
            'MaxConcurrentLocalActivityExecutionSize' => 0,
            'WorkerLocalActivitiesPerSecond' => 0.0,
            'TaskQueueActivitiesPerSecond' => 0.0,
            'MaxConcurrentActivityTaskPollers' => 0,
            'MaxConcurrentWorkflowTaskExecutionSize' => 0,
            'MaxConcurrentWorkflowTaskPollers' => 0,
            'MaxConcurrentNexusTaskExecutionSize' => 0,
            'MaxConcurrentNexusTaskPollers' => 0,
            'EnableLoggingInReplay' => false,
            'StickyScheduleToStartTimeout' => null,
            'WorkflowPanicPolicy' => 0,
            'WorkerStopTimeout' => null,
            'EnableSessionWorker' => false,
            'SessionResourceID' => null,
            'MaxConcurrentSessionExecutionSize' => 1000,
            'DisableWorkflowWorker' => false,
            'LocalActivityWorkerOnly' => false,
            'Identity' => "",
            'DeadlockDetectionTimeout' => null,
            'MaxHeartbeatThrottleInterval' => null,
            'DisableEagerActivities' => false,
            'MaxConcurrentEagerActivityExecutionSize' => 0,
            'DisableRegistrationAliasing' => false,
            'BuildID' => "",
            'DeploymentOptions' => null,
            'UseBuildIDForVersioning' => false,
        ];

        $this->assertEquals($expected, $this->marshal($dto));
    }

    public function testDeploymentOptionsNoUse(): void
    {
        $dto = new WorkerOptions();
        $result = $dto->withDeploymentOptions(
            WorkerDeploymentOptions::new()
                ->withUseVersioning(false),
        );

        self::assertNotSame($dto, $result);
        $options = $this->marshal($result)['DeploymentOptions'];

        self::assertFalse($options['UseVersioning']);
        self::assertSame(VersioningBehavior::Unspecified->value, $options['DefaultVersioningBehavior']);
        self::assertNull($options['Version']);
    }

    public function testDeploymentOptionsUseVersion(): void
    {
        $dto = new WorkerOptions();
        $result = $dto->withDeploymentOptions(
            WorkerDeploymentOptions::new()
                ->withUseVersioning(true)
                ->withVersion(WorkerDeploymentVersion::new('foo', 'bar'))
                ->withDefaultVersioningBehavior(VersioningBehavior::AutoUpgrade),
        );

        self::assertNotSame($dto, $result);
        $options = $this->marshal($result)['DeploymentOptions'];

        self::assertTrue($options['UseVersioning']);
        self::assertSame(VersioningBehavior::AutoUpgrade->value, $options['DefaultVersioningBehavior']);
        self::assertSame('foo.bar', $options['Version']);
    }

    public function testMaxConcurrentActivityExecutionSize(): void
    {
        $dto = new WorkerOptions();
        $result = $dto->withMaxConcurrentActivityExecutionSize(10);

        self::assertNotSame($dto, $result);
        self::assertSame(0, $dto->maxConcurrentActivityExecutionSize);
        self::assertSame(10, $result->maxConcurrentActivityExecutionSize);
    }

    public function testWorkerActivitiesPerSecond(): void
    {
        $dto = new WorkerOptions();
        $result = $dto->withWorkerActivitiesPerSecond(10.0);

        self::assertNotSame($dto, $result);
        self::assertSame(0.0, $dto->workerActivitiesPerSecond);
        self::assertSame(10.0, $result->workerActivitiesPerSecond);
    }

    public function testMaxConcurrentLocalActivityExecutionSize(): void
    {
        $dto = new WorkerOptions();
        $result = $dto->withMaxConcurrentLocalActivityExecutionSize(10);

        self::assertNotSame($dto, $result);
        self::assertSame(0, $dto->maxConcurrentLocalActivityExecutionSize);
        self::assertSame(10, $result->maxConcurrentLocalActivityExecutionSize);
    }

    public function testWorkerLocalActivitiesPerSecond(): void
    {
        $dto = new WorkerOptions();
        $result = $dto->withWorkerLocalActivitiesPerSecond(10.0);

        self::assertNotSame($dto, $result);
        self::assertSame(0.0, $dto->workerLocalActivitiesPerSecond);
        self::assertSame(10.0, $result->workerLocalActivitiesPerSecond);
    }

    public function testTaskQueueActivitiesPerSecond(): void
    {
        $dto = new WorkerOptions();
        $result = $dto->withTaskQueueActivitiesPerSecond(10.0);

        self::assertNotSame($dto, $result);
        self::assertSame(0.0, $dto->taskQueueActivitiesPerSecond);
        self::assertSame(10.0, $result->taskQueueActivitiesPerSecond);
    }

    public function testMaxConcurrentActivityTaskPollers(): void
    {
        $dto = new WorkerOptions();
        $result = $dto->withMaxConcurrentActivityTaskPollers(10);

        self::assertNotSame($dto, $result);
        self::assertSame(0, $dto->maxConcurrentActivityTaskPollers);
        self::assertSame(10, $result->maxConcurrentActivityTaskPollers);
    }

    public function testMaxConcurrentWorkflowTaskExecutionSize(): void
    {
        $dto = new WorkerOptions();
        $result = $dto->withMaxConcurrentWorkflowTaskExecutionSize(10);

        self::assertNotSame($dto, $result);
        self::assertSame(0, $dto->maxConcurrentWorkflowTaskExecutionSize);
        self::assertSame(10, $result->maxConcurrentWorkflowTaskExecutionSize);
    }

    public function testMaxConcurrentWorkflowTaskPollers(): void
    {
        $dto = new WorkerOptions();
        $result = $dto->withMaxConcurrentWorkflowTaskPollers(10);

        self::assertNotSame($dto, $result);
        self::assertSame(0, $dto->maxConcurrentWorkflowTaskPollers);
        self::assertSame(10, $result->maxConcurrentWorkflowTaskPollers);
    }

    public function testMaxConcurrentNexusTaskExecutionSize(): void
    {
        $dto = new WorkerOptions();
        $result = $dto->withMaxConcurrentNexusTaskExecutionSize(10);

        self::assertNotSame($dto, $result);
        self::assertSame(0, $dto->maxConcurrentNexusTaskExecutionSize);
        self::assertSame(10, $result->maxConcurrentNexusTaskExecutionSize);
    }

    public function testMaxConcurrentNexusTaskPollers(): void
    {
        $dto = new WorkerOptions();
        $result = $dto->withMaxConcurrentNexusTaskPollers(10);

        self::assertNotSame($dto, $result);
        self::assertSame(0, $dto->maxConcurrentNexusTaskPollers);
        self::assertSame(10, $result->maxConcurrentNexusTaskPollers);
    }

    public function testEnableLoggingInReplay(): void
    {
        $dto = new WorkerOptions();
        $result = $dto->withEnableLoggingInReplay(true);

        self::assertNotSame($dto, $result);
        self::assertFalse($dto->enableLoggingInReplay);
        self::assertTrue($result->enableLoggingInReplay);
    }

    public function testStickyScheduleToStartTimeout(): void
    {
        $dto = new WorkerOptions();
        $result = $dto->withStickyScheduleToStartTimeout(10);

        self::assertNotSame($dto, $result);
        self::assertNull($dto->stickyScheduleToStartTimeout);
        self::assertSame(10, $result->stickyScheduleToStartTimeout->seconds);
    }

    public function testWorkflowPanicPolicy(): void
    {
        $dto = new WorkerOptions();
        $result = $dto->withWorkflowPanicPolicy(WorkflowPanicPolicy::FailWorkflow);

        self::assertNotSame($dto, $result);
        self::assertSame(WorkflowPanicPolicy::BlockWorkflow, $dto->workflowPanicPolicy);
        self::assertSame(WorkflowPanicPolicy::FailWorkflow, $result->workflowPanicPolicy);
    }

    public function testWorkerStopTimeout(): void
    {
        $dto = new WorkerOptions();
        $result = $dto->withWorkerStopTimeout(10);

        self::assertNotSame($dto, $result);
        self::assertNull($dto->workerStopTimeout);
        self::assertSame(10, $result->workerStopTimeout->seconds);
    }

    public function testEnableSessionWorker(): void
    {
        $dto = new WorkerOptions();
        $result = $dto->withEnableSessionWorker(true);

        self::assertNotSame($dto, $result);
        self::assertFalse($dto->enableSessionWorker);
        self::assertTrue($result->enableSessionWorker);
    }

    public function testSessionResourceID(): void
    {
        $dto = new WorkerOptions();
        $result = $dto->withSessionResourceID('test');

        self::assertNotSame($dto, $result);
        self::assertNull($dto->sessionResourceId);
        self::assertSame('test', $result->sessionResourceId);
    }

    public function testMaxConcurrentSessionExecutionSize(): void
    {
        $dto = new WorkerOptions();
        $result = $dto->withMaxConcurrentSessionExecutionSize(10);

        self::assertNotSame($dto, $result);
        self::assertSame(1000, $dto->maxConcurrentSessionExecutionSize);
        self::assertSame(10, $result->maxConcurrentSessionExecutionSize);
    }

    public function testDisableWorkflowWorker(): void
    {
        $dto = new WorkerOptions();
        $result = $dto->withDisableWorkflowWorker(true);

        self::assertNotSame($dto, $result);
        self::assertFalse($dto->disableWorkflowWorker);
        self::assertTrue($result->disableWorkflowWorker);
    }

    public function testLocalActivityWorkerOnly(): void
    {
        $dto = new WorkerOptions();
        $result = $dto->withLocalActivityWorkerOnly(true);

        self::assertNotSame($dto, $result);
        self::assertFalse($dto->localActivityWorkerOnly);
        self::assertTrue($result->localActivityWorkerOnly);
    }

    public function testIdentity(): void
    {
        $dto = new WorkerOptions();
        $result = $dto->withIdentity('test');

        self::assertNotSame($dto, $result);
        self::assertSame('', $dto->identity);
        self::assertSame('test', $result->identity);
    }

    public function testDeadlockDetectionTimeout(): void
    {
        $dto = new WorkerOptions();
        $result = $dto->withDeadlockDetectionTimeout(10);

        self::assertNotSame($dto, $result);
        self::assertNull($dto->deadlockDetectionTimeout);
        self::assertSame(10, $result->deadlockDetectionTimeout->seconds);
    }

    public function testMaxHeartbeatThrottleInterval(): void
    {
        $dto = new WorkerOptions();
        $result = $dto->withMaxHeartbeatThrottleInterval(10);

        self::assertNotSame($dto, $result);
        self::assertNull($dto->maxHeartbeatThrottleInterval);
        self::assertSame(10, $result->maxHeartbeatThrottleInterval->seconds);
    }

    public function testDisableEagerActivities(): void
    {
        $dto = new WorkerOptions();
        $result = $dto->withDisableEagerActivities(true);

        self::assertNotSame($dto, $result);
        self::assertFalse($dto->disableEagerActivities);
        self::assertTrue($result->disableEagerActivities);
    }

    public function testMaxConcurrentEagerActivityExecutionSize(): void
    {
        $dto = new WorkerOptions();
        $result = $dto->withMaxConcurrentEagerActivityExecutionSize(10);

        self::assertNotSame($dto, $result);
        self::assertSame(0, $dto->maxConcurrentEagerActivityExecutionSize);
        self::assertSame(10, $result->maxConcurrentEagerActivityExecutionSize);
    }

    public function testDisableRegistrationAliasing(): void
    {
        $dto = new WorkerOptions();
        $result = $dto->withDisableRegistrationAliasing(true);

        self::assertNotSame($dto, $result);
        self::assertFalse($dto->disableRegistrationAliasing);
        self::assertTrue($result->disableRegistrationAliasing);
    }

    public function testBuildID(): void
    {
        $dto = new WorkerOptions();
        $result = $dto->withBuildID('test');

        self::assertNotSame($dto, $result);
        self::assertSame('', $dto->buildID);
        self::assertSame('test', $result->buildID);
    }

    public function testUseBuildIDForVersioning(): void
    {
        $dto = new WorkerOptions();
        $result = $dto->withUseBuildIDForVersioning(true);

        self::assertNotSame($dto, $result);
        self::assertFalse($dto->useBuildIDForVersioning);
        self::assertTrue($result->useBuildIDForVersioning);
    }
}
