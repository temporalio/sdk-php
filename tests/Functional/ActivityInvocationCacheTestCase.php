<?php

declare(strict_types=1);

namespace Temporal\Tests\Functional;

use Exception;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Exception\Failure\ActivityFailure;
use Temporal\Worker\ActivityInvocationCache\RoadRunnerActivityInvocationCache;
use Temporal\Worker\Transport\Command\Request;
use Temporal\Worker\Transport\Command\RequestInterface;

class ActivityInvocationCacheTestCase extends FunctionalTestCase
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
        $result->then(
            fn($value) => $this->assertSame('foo', $value),
            fn(Exception $exception) => $this->fail($exception->getMessage())
        );
    }

    public function testActivityFailureIsStoredInCache(): void
    {
        $this->cache->saveFailure('MyActivity.myMethod', new \LogicException('some error'));
        $result = $this->cache->execute($this->makeRequest('InvokeActivity', 'MyActivity.myMethod', EncodedValues::empty()));
        $result->then(
            fn(Exception $exception) => $this->fail('Activity should fail'),
            function (Exception $exception) {
                $this->assertInstanceOf(ActivityFailure::class, $exception);
                $this->assertSame('some error', $exception->getMessage());
            }
        );
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

    private function makeRequest(string $name, string $activityName, ValuesInterface $values): RequestInterface
    {
        $options = [
            'name' => $activityName,
            'info' => [
                'TaskToken' => 'CiQ2ODM5YzcwOS05MGQwLTQ2ZjktOTYyYS03NTM3OWJhMWQ4MzcSJDQ5NDI1YjgwLTAwNTctNDA5Ni04ZWQyLTJmZjMzMzY5MmM3YxokOTI2MGFlZTMtYzhhMC00ZTMxLWI3ZWUtNWQ2NTZhYWEzMjZiIAUoATIBNUITU2ltcGxlQWN0aXZpdHkuZWNobw==',
                'ActivityType' => ['Name' => $activityName],
            ]
        ];
        return new Request($name, $options, $values);
    }
}
