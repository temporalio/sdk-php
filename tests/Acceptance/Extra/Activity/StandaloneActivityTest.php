<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Activity\StandaloneActivity;

use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Temporal\Activity;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Client\Activity\ActivityExecutionStatus;
use Temporal\Client\Activity\StartActivityOptions;
use Temporal\Client\ActivityClientInterface;
use Temporal\Common\ActivityIdConflictPolicy;
use Temporal\Exception\Client\ActivityAlreadyStartedException;
use Temporal\Exception\Client\ActivityFailedException;
use Temporal\Exception\Client\TimeoutException;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Exception\Failure\TerminatedFailure;
use Temporal\Tests\Acceptance\App\Runtime\Feature;
use Temporal\Tests\Acceptance\App\TestCase;

class StandaloneActivityTest extends TestCase
{
    #[Test]
    public function getResult(ActivityClientInterface $client, Feature $feature): void
    {
        $handle = $client->start('Extra_Activity_StandaloneActivity.echo', $this->options($feature), 'hello');

        self::assertNotSame('', (string) $handle->getRunId());
        self::assertSame('hello', $handle->getResult('string'));
    }

    #[Test]
    public function activityInfo(ActivityClientInterface $client, Feature $feature): void
    {
        $handle = $client->start('Extra_Activity_StandaloneActivity.info', $this->options($feature));

        $info = $handle->getResult('array');

        self::assertSame($handle->getId(), $info['id']);
        self::assertSame($handle->getRunId(), $info['activityRunId']);
        self::assertSame('', (string) $info['workflowId']);
        self::assertNotSame('', $info['namespace']);
    }

    #[Test]
    public function failure(ActivityClientInterface $client, Feature $feature): void
    {
        $handle = $client->start('Extra_Activity_StandaloneActivity.fail', $this->options($feature));

        try {
            $handle->getResult();
            self::fail('Expected ActivityFailedException');
        } catch (ActivityFailedException $e) {
            self::assertSame($handle->getId(), $e->activityId);
            $previous = $e->getPrevious();
            self::assertInstanceOf(ApplicationFailure::class, $previous);
            self::assertSame('TestError', $previous->getType());
        }
    }

    #[Test]
    public function describe(ActivityClientInterface $client, Feature $feature): void
    {
        $handle = $client->start(
            'Extra_Activity_StandaloneActivity.echo',
            $this->options($feature)->withStaticSummary('summary'),
            'hello',
        );
        $handle->getResult();

        $description = $handle->describe();

        self::assertSame($handle->getId(), $description->info->activityId);
        self::assertSame($handle->getRunId(), $description->info->runId);
        self::assertSame('Extra_Activity_StandaloneActivity.echo', $description->info->activityType);
        self::assertSame($feature->taskQueue, $description->info->taskQueue);
        self::assertSame(ActivityExecutionStatus::Completed, $description->info->status);
        self::assertSame('summary', $description->staticSummary);
    }

    #[Test]
    public function alreadyStarted(ActivityClientInterface $client, Feature $feature): void
    {
        $options = $this->options($feature)->withIdConflictPolicy(ActivityIdConflictPolicy::Fail);
        $handle = $client->start('Extra_Activity_StandaloneActivity.block', $options);

        try {
            $client->start('Extra_Activity_StandaloneActivity.block', $options);
            self::fail('Expected ActivityAlreadyStartedException');
        } catch (ActivityAlreadyStartedException $e) {
            self::assertSame($handle->getId(), $e->activityId);
            self::assertSame($handle->getRunId(), $e->runId);
        } finally {
            $handle->terminate();
        }
    }

    #[Test]
    public function terminate(ActivityClientInterface $client, Feature $feature): void
    {
        $handle = $client->start('Extra_Activity_StandaloneActivity.block', $this->options($feature));

        $handle->terminate('stop');

        try {
            $handle->getResult();
            self::fail('Expected ActivityFailedException');
        } catch (ActivityFailedException $e) {
            self::assertInstanceOf(TerminatedFailure::class, $e->getPrevious());
        }
        self::assertSame(ActivityExecutionStatus::Terminated, $handle->describe()->info->status);
    }

    #[Test]
    public function cancel(ActivityClientInterface $client, Feature $feature): void
    {
        $handle = $client->start(
            'Extra_Activity_StandaloneActivity.block',
            $this->options($feature)->withHeartbeatTimeout(5),
        );

        $handle->cancel('stop');

        try {
            $handle->getResult();
            self::fail('Expected ActivityFailedException');
        } catch (ActivityFailedException $e) {
            self::assertInstanceOf(CanceledFailure::class, $e->getPrevious());
        }
        self::assertSame('stop', $handle->describe()->canceledReason);
    }

    #[Test]
    public function getResultTimeout(ActivityClientInterface $client, Feature $feature): void
    {
        $handle = $client->start('Extra_Activity_StandaloneActivity.block', $this->options($feature));

        $start = \microtime(true);
        try {
            $handle->getResult(timeout: 1);
            self::fail('Expected TimeoutException');
        } catch (TimeoutException) {
            self::assertLessThan(3, \microtime(true) - $start);
            self::assertSame(ActivityExecutionStatus::Running, $handle->describe()->info->status);
        } finally {
            $handle->terminate();
        }
    }

    #[Test]
    public function listAndCount(ActivityClientInterface $client, Feature $feature): void
    {
        $handle = $client->start('Extra_Activity_StandaloneActivity.echo', $this->options($feature), 'hello');
        $handle->getResult();
        $query = \sprintf("ActivityId = '%s'", $handle->getId());

        $deadline = \microtime(true) + 10;
        while ($client->countExecutions($query)->count === 0 && \microtime(true) < $deadline) {
            \usleep(200_000);
        }

        self::assertSame(1, $client->countExecutions($query)->count);
        $items = $client->listExecutions($query)->getPageItems();
        self::assertCount(1, $items);
        self::assertSame($handle->getId(), $items[0]->activityId);
        self::assertSame($handle->getRunId(), $items[0]->runId);
    }

    private function options(Feature $feature): StartActivityOptions
    {
        return StartActivityOptions::new()
            ->withId(Uuid::uuid4()->toString())
            ->withTaskQueue($feature->taskQueue)
            ->withStartToCloseTimeout(30);
    }
}

#[ActivityInterface(prefix: 'Extra_Activity_StandaloneActivity.')]
class TestActivity
{
    #[ActivityMethod]
    public function echo(string $value): string
    {
        return $value;
    }

    #[ActivityMethod]
    public function info(): array
    {
        $info = Activity::getInfo();

        return [
            'id' => $info->id,
            'activityRunId' => $info->activityRunId,
            'workflowId' => $info->workflowExecution?->getID(),
            'namespace' => $info->namespace,
        ];
    }

    #[ActivityMethod]
    public function fail(): void
    {
        throw new ApplicationFailure('boom', 'TestError', true);
    }

    #[ActivityMethod]
    public function block(): void
    {
        for ($i = 0; $i < 30; $i++) {
            Activity::heartbeat($i);
            \sleep(1);
        }
    }
}
