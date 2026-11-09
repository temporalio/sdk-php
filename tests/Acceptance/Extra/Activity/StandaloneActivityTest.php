<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Activity\StandaloneActivity;

use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Temporal\Activity;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Client\Activity\ActivityExecutionDescription;
use Temporal\Client\Activity\ActivityHandle;
use Temporal\Common\ActivityIdReusePolicy;
use Temporal\Common\RetryOptions;
use Temporal\Exception\Client\ActivityNotFoundException;
use Temporal\Tests\Acceptance\App\Runtime\State;
use Temporal\Client\Activity\ActivityExecutionStatus;
use Temporal\Client\Activity\StartActivityOptions;
use Temporal\Client\ActivityClientInterface;
use Temporal\Common\ActivityIdConflictPolicy;
use Temporal\Exception\Client\ActivityAlreadyStartedException;
use Temporal\Exception\Client\ActivityFailedException;
use Temporal\Exception\Client\TimeoutException;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Exception\Failure\TemporalFailure;
use Temporal\Exception\Failure\TerminatedFailure;
use Temporal\Tests\Acceptance\App\Runtime\Feature;
use Temporal\Tests\Acceptance\App\TestCase;

class StandaloneActivityTest extends TestCase
{
    #[Test]
    public function getResult(ActivityClientInterface $client, Feature $feature): void
    {
        $handle = $client->start('Extra_Activity_StandaloneActivity.echo', $this->options($feature), 'hello');

        self::assertNotSame('', (string) $handle->getRunID());
        self::assertSame('hello', $handle->getResult('string'));
    }

    #[Test]
    public function activityInfo(ActivityClientInterface $client, Feature $feature, State $runtime): void
    {
        $handle = $client->start('Extra_Activity_StandaloneActivity.info', $this->options($feature));

        $info = $handle->getResult('array');

        self::assertSame($handle->getID(), $info['id']);
        self::assertSame($handle->getRunID(), $info['activityRunId']);
        self::assertSame('', (string) $info['workflowId']);
        self::assertSame($runtime->namespace, $info['namespace']);
    }

    #[Test]
    public function failure(ActivityClientInterface $client, Feature $feature): void
    {
        $handle = $client->start('Extra_Activity_StandaloneActivity.fail', $this->options($feature));

        try {
            $handle->getResult();
            self::fail('Expected ActivityFailedException');
        } catch (ActivityFailedException $e) {
            self::assertSame($handle->getID(), $e->activityId);
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

        self::assertSame($handle->getID(), $description->info->activityId);
        self::assertSame($handle->getRunID(), $description->info->runId);
        self::assertSame('Extra_Activity_StandaloneActivity.echo', $description->info->activityType);
        self::assertSame($feature->taskQueue, $description->info->taskQueue);
        self::assertSame(ActivityExecutionStatus::Completed, $description->info->status);
        self::assertSame('summary', $description->userMetadata->summary);
    }

    #[Test]
    public function alreadyStarted(ActivityClientInterface $client, Feature $feature): void
    {
        $options = $this->options($feature)->withIdConflictPolicy(ActivityIdConflictPolicy::Fail);
        $handle = $client->start('Extra_Activity_StandaloneActivity.block', $options, 5);

        try {
            $client->start('Extra_Activity_StandaloneActivity.block', $options, 5);
            self::fail('Expected ActivityAlreadyStartedException');
        } catch (ActivityAlreadyStartedException $e) {
            self::assertSame($handle->getID(), $e->activityId);
            self::assertSame($handle->getRunID(), $e->runId);
        } finally {
            $handle->terminate();
        }
    }

    #[Test]
    public function terminate(ActivityClientInterface $client, Feature $feature): void
    {
        $handle = $client->start('Extra_Activity_StandaloneActivity.block', $this->options($feature), 5);

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
        $handle = $client->start('Extra_Activity_StandaloneActivity.block', $this->options($feature), 5);

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
    public function describeReturnsHeartbeatDetails(ActivityClientInterface $client, Feature $feature): void
    {
        $handle = $client->start(
            'Extra_Activity_StandaloneActivity.block',
            $this->options($feature)->withHeartbeatTimeout(5),
            10,
        );

        try {
            $description = $this->waitDescription(
                $handle,
                static fn(ActivityExecutionDescription $d): bool => $d->totalHeartbeatCount > 0,
            );

            self::assertIsInt($description->heartbeatDetails->getValue(0, 'int'));
            self::assertNotNull($description->lastHeartbeatTime);
        } finally {
            $handle->terminate();
        }
    }

    #[Test]
    public function describeReturnsLastFailure(ActivityClientInterface $client, Feature $feature): void
    {
        $handle = $client->start(
            'Extra_Activity_StandaloneActivity.failRetryable',
            $this->options($feature)->withRetryOptions(
                RetryOptions::new()->withInitialInterval(30)->withMaximumAttempts(3),
            ),
        );

        try {
            $description = $this->waitDescription(
                $handle,
                static fn(ActivityExecutionDescription $d): bool => $d->lastFailure !== null,
            );

            self::assertInstanceOf(TemporalFailure::class, $description->lastFailure);
            self::assertSame(3, $description->retryPolicy?->maximumAttempts);
        } finally {
            $handle->terminate();
        }
    }

    #[Test]
    public function getHandleResolvesRunsOfReusedId(ActivityClientInterface $client, Feature $feature): void
    {
        $options = $this->options($feature)->withIdReusePolicy(ActivityIdReusePolicy::AllowDuplicate);
        $first = $client->start('Extra_Activity_StandaloneActivity.echo', $options, 'first');
        $first->getResult();
        $second = $client->start('Extra_Activity_StandaloneActivity.echo', $options, 'second');
        $second->getResult();

        self::assertNotSame($first->getRunID(), $second->getRunID());
        self::assertSame('second', $client->getHandle($options->id)->getResult('string'));
        self::assertSame('first', $client->getHandle($options->id, $first->getRunID())->getResult('string'));
    }

    #[Test]
    public function rejectDuplicate(ActivityClientInterface $client, Feature $feature): void
    {
        $options = $this->options($feature)->withIdReusePolicy(ActivityIdReusePolicy::RejectDuplicate);
        $handle = $client->start('Extra_Activity_StandaloneActivity.echo', $options, 'hello');
        $handle->getResult();

        try {
            $client->start('Extra_Activity_StandaloneActivity.echo', $options, 'hello');
            self::fail('Expected ActivityAlreadyStartedException');
        } catch (ActivityAlreadyStartedException $e) {
            self::assertSame($options->id, $e->activityId);
        }
    }

    #[Test]
    public function useExistingReturnsRunningExecution(ActivityClientInterface $client, Feature $feature): void
    {
        $options = $this->options($feature)->withIdConflictPolicy(ActivityIdConflictPolicy::UseExisting);
        $handle = $client->start('Extra_Activity_StandaloneActivity.block', $options, 5);

        try {
            $existing = $client->start('Extra_Activity_StandaloneActivity.block', $options, 5);

            self::assertSame($handle->getRunID(), $existing->getRunID());
        } finally {
            $handle->terminate();
        }
    }

    #[Test]
    public function startDelay(ActivityClientInterface $client, Feature $feature): void
    {
        $handle = $client->start(
            'Extra_Activity_StandaloneActivity.echo',
            $this->options($feature)->withStartDelay(2),
            'hello',
        );
        $handle->getResult();

        $description = $handle->describe();

        self::assertSame(2, (int) $description->startDelay?->totalSeconds);
        self::assertNotNull($description->info->scheduleTime);
        self::assertNotNull($description->lastStartedTime);
        self::assertGreaterThanOrEqual(
            2,
            $description->lastStartedTime->getTimestamp() - $description->info->scheduleTime->getTimestamp(),
        );
    }

    #[Test]
    public function completeByActivityId(ActivityClientInterface $client, Feature $feature): void
    {
        $handle = $client->start('Extra_Activity_StandaloneActivity.async', $this->options($feature));
        $this->waitDescription(
            $handle,
            static fn(ActivityExecutionDescription $d): bool => $d->lastStartedTime !== null,
        );

        $client->newActivityCompletionClient()->complete('', $handle->getRunID(), $handle->getID(), 'done');

        self::assertSame('done', $handle->getResult('string'));
    }

    #[Test]
    public function notFound(ActivityClientInterface $client): void
    {
        $this->expectException(ActivityNotFoundException::class);

        $client->getHandle(Uuid::uuid4()->toString())->describe();
    }

    #[Test]
    public function listAndCount(ActivityClientInterface $client, Feature $feature): void
    {
        $handle = $client->start('Extra_Activity_StandaloneActivity.echo', $this->options($feature), 'hello');
        $handle->getResult();
        $query = \sprintf("ActivityId = '%s'", $handle->getID());

        $deadline = \microtime(true) + 10;
        while ($client->countExecutions($query)->count === 0 && \microtime(true) < $deadline) {
            \usleep(200_000);
        }

        self::assertSame(1, $client->countExecutions($query)->count);
        $items = $client->listExecutions($query)->getPageItems();
        self::assertCount(1, $items);
        self::assertSame($handle->getID(), $items[0]->activityId);
        self::assertSame($handle->getRunID(), $items[0]->runId);
    }

    /**
     * @param \Closure(ActivityExecutionDescription): bool $condition
     */
    private function waitDescription(ActivityHandle $handle, \Closure $condition): ActivityExecutionDescription
    {
        $deadline = \microtime(true) + 30;
        do {
            $description = $handle->describe();
            if ($condition($description)) {
                return $description;
            }
            \usleep(200_000);
        } while (\microtime(true) < $deadline);

        self::fail('Activity description did not reach the expected state');
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
    public function failRetryable(): void
    {
        throw new ApplicationFailure('boom', 'TestError', false);
    }

    #[ActivityMethod]
    public function async(): void
    {
        Activity::doNotCompleteOnReturn();
    }

    #[ActivityMethod]
    public function block(int $seconds = 30): void
    {
        for ($i = 0; $i < $seconds; $i++) {
            Activity::heartbeat($i);
            \sleep(1);
        }
    }
}
