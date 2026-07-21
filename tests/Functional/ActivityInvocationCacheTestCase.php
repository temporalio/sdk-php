<?php

declare(strict_types=1);

namespace Temporal\Tests\Functional;

use DateTimeImmutable;
use Exception;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Worker\ActivityInvocationCache\RoadRunnerActivityInvocationCache;
use Temporal\Worker\Transport\Command\Server\ServerRequest;
use Temporal\Worker\Transport\Command\Server\TickInfo;
use Temporal\Worker\Transport\Command\ServerRequestInterface;

class ActivityInvocationCacheTestCase extends AbstractFunctional
{
    private RoadRunnerActivityInvocationCache $cache;

    protected function setUp(): void
    {
        $this->cache = RoadRunnerActivityInvocationCache::create();
        parent::setUp();
    }

    public function testActivityCompletionIsStoredInCache(): void
    {
        $this->cache->saveCompletion('MyActivity.myMethod', 'foo');
        $result = $this->cache->execute($this->makeRequest('InvokeActivity', 'MyActivity.myMethod', EncodedValues::empty()));

        $value = null;
        $rejected = null;
        $result->then(
            function ($resolved) use (&$value): void {
                $value = $resolved;
            },
            function (\Throwable $exception) use (&$rejected): void {
                $rejected = $exception;
            },
        );

        self::assertNull($rejected, 'Completion should resolve, not reject');
        self::assertInstanceOf(EncodedValues::class, $value);
        self::assertSame('foo', $value->getValue(0, 'string'));
    }

    public function testActivityFailureIsStoredInCache(): void
    {
        $this->cache->saveFailure('MyActivity.myMethod', new \LogicException('some error'));
        $result = $this->cache->execute($this->makeRequest('InvokeActivity', 'MyActivity.myMethod', EncodedValues::empty()));

        $rejected = null;
        $resolved = false;
        $result->then(
            function ($value) use (&$resolved): void {
                $resolved = true;
            },
            function (\Throwable $exception) use (&$rejected): void {
                $rejected = $exception;
            },
        );

        self::assertFalse($resolved, 'Activity should fail, not resolve');
        self::assertInstanceOf(ApplicationFailure::class, $rejected);
        self::assertSame('LogicException', $rejected->getType());
        self::assertSame('some error', $rejected->getOriginalMessage());
    }

    public function testCacheCanHandleStoredResult(): void
    {
        $this->cache->saveFailure('MyActivity.myMethod', new \LogicException('some error'));
        $this->assertTrue($this->cache->canHandle($this->makeRequest('InvokeActivity', 'MyActivity.myMethod', EncodedValues::empty())));
    }

    public function testCacheCannotHandleStoredResult(): void
    {
        $this->cache->saveFailure('MyActivity.myMethod', new \LogicException('some error'));
        $this->assertFalse($this->cache->canHandle($this->makeRequest('StartWorkflow', 'MyActivity.myMethod', EncodedValues::empty())));
    }

    private function makeRequest(string $name, string $activityName, ValuesInterface $values): ServerRequestInterface
    {
        $options = [
            'name' => $activityName,
            'info' => [
                'TaskToken' => 'CiQ2ODM5YzcwOS05MGQwLTQ2ZjktOTYyYS03NTM3OWJhMWQ4MzcSJDQ5NDI1YjgwLTAwNTctNDA5Ni04ZWQyLTJmZjMzMzY5MmM3YxokOTI2MGFlZTMtYzhhMC00ZTMxLWI3ZWUtNWQ2NTZhYWEzMjZiIAUoATIBNUITU2ltcGxlQWN0aXZpdHkuZWNobw==',
                'ActivityType' => ['Name' => $activityName],
            ]
        ];
        $info = new TickInfo(new DateTimeImmutable());
        return new ServerRequest(name: $name, info: $info, options: $options, payloads:  $values);
    }
}
