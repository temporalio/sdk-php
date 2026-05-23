<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App;

use Temporal\Tests\Acceptance\App\Attribute\Worker;

final class TaskQueueResolver
{
    public const DEFAULT_TASK_QUEUE = 'default';

    /**
     * @var list<class-string>
     */
    private const SHARED_QUEUE_EXCLUSIONS = [
        \Temporal\Tests\Acceptance\Extra\Workflow\WorkflowA\WorkflowATest::class,
        \Temporal\Tests\Acceptance\Extra\Workflow\WorkflowB\WorkflowBTest::class,
        \Temporal\Tests\Acceptance\Harness\Activity\RetryOnError\RetryOnErrorTest::class,
        \Temporal\Tests\Acceptance\Harness\Update\Self\SelfTest::class,
        \Temporal\Tests\Acceptance\Harness\Update\Activities\ActivitiesTest::class,
        \Temporal\Tests\Acceptance\Harness\Signal\Activities\ActivitiesTest::class,
        \Temporal\Tests\Acceptance\Extra\Versioning\Classic\ClassicTest::class,
        \Temporal\Tests\Acceptance\Extra\Versioning\Deployment\DeploymentTest::class,
        \Temporal\Tests\Acceptance\Extra\Versioning\Fibers\Classic\ClassicTest::class,
        \Temporal\Tests\Acceptance\Extra\Versioning\Fibers\Deployment\DeploymentTest::class,
    ];

    /**
     * @param class-string $class
     * @param non-empty-string $namespace
     * @return non-empty-string
     */
    public static function resolve(string $class, string $namespace): string
    {
        if (\in_array($class, self::SHARED_QUEUE_EXCLUSIONS, true)) {
            return $namespace;
        }

        $reflection = new \ReflectionClass($class);
        foreach ($reflection->getAttributes(Worker::class) as $attribute) {
            $worker = $attribute->newInstance();
            if ($worker->pipelineProvider !== null
                || $worker->logger !== null
                || $worker->plugins !== null
            ) {
                return $namespace;
            }
        }

        return self::DEFAULT_TASK_QUEUE;
    }
}
