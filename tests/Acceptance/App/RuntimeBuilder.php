<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App;

use Temporal\Nexus\Attribute\Service as NexusService;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Activity\ActivityInterface;
use Temporal\DataConverter\PayloadConverterInterface;
use Temporal\Testing\DeprecationCollector;
use Temporal\Testing\Command;
use Temporal\Tests\Acceptance\App\Input\Feature;
use Temporal\Tests\Acceptance\App\Runtime\State;
use Temporal\Worker\FeatureFlags;
use Temporal\Workflow\WorkflowInterface;

final class RuntimeBuilder
{
    public static function hydrateClasses(State $runtime): void
    {
        foreach ($runtime->testCasesDir as $namespace => $dir) {
            foreach (self::iterateClasses($dir, $namespace) as $feature => $classes) {
                foreach ($classes as $classString) {
                    $class = new \ReflectionClass($classString);

                    # Register Workflow
                    if ($class->getAttributes(WorkflowInterface::class) !== []) {
                        $runtime->addWorkflow($feature, $classString);
                    }

                    # Register Activity
                    if ($class->getAttributes(ActivityInterface::class) !== []) {
                        $runtime->addActivity($feature, $classString);
                    }

                    # Register Nexus Service: any non-interface class that either carries
                    # #[Service] directly or implements an interface annotated with #[Service]
                    # (mirrors Workflow/Activity discovery — see WorkflowReader / ActivityReader).
                    if (!$class->isInterface() && !$class->isAbstract()) {
                        if ($class->getAttributes(NexusService::class) !== []) {
                            $runtime->addNexusService($feature, $classString);
                        } else {
                            foreach ($class->getInterfaces() as $interface) {
                                if ($interface->getAttributes(NexusService::class) !== []) {
                                    $runtime->addNexusService($feature, $classString);
                                    break;
                                }
                            }
                        }
                    }

                    # Register Converters
                    if ($class->implementsInterface(PayloadConverterInterface::class)) {
                        $runtime->addConverter($feature, $classString);
                    }

                    # Register Check
                    foreach ($class->getMethods() as $method) {
                        if ($method->getAttributes(Test::class) !== []) {
                            $runtime->addCheck($feature, $classString, $method->getName());
                        }
                    }
                }
            }
        }
    }

    /**
     * @param non-empty-string $workDir
     * @param iterable<non-empty-string, non-empty-string> $testCasesDir
     */
    public static function createEmpty(Command $command, string $workDir, iterable $testCasesDir, int $workers = 1): State
    {
        return new State($command, \dirname(__DIR__), $workDir, $testCasesDir, $workers);
    }

    /**
     * @param non-empty-string $workDir
     * @param iterable<non-empty-string, non-empty-string> $testCasesDir
     */
    public static function createState(Command $command, string $workDir, iterable $testCasesDir, int $workers = 1): State
    {
        $runtime = new State($command, \dirname(__DIR__), $workDir, $testCasesDir, $workers);

        self::hydrateClasses($runtime);

        return $runtime;
    }

    public static function init(): void
    {
        \ini_set('display_errors', 'stderr');
        error_reporting(-1);
        DeprecationCollector::register();
        // Feature flags
        FeatureFlags::$workflowDeferredHandlerStart = true;
        FeatureFlags::$cancelAbandonedChildWorkflows = false;
        FeatureFlags::$warnOnActivityMethodWithoutAttribute = true;
    }

    /**
     * @param non-empty-string $featuresDir
     * @param non-empty-string $ns
     * @return iterable<Feature, array<int, class-string>>
     */
    private static function iterateClasses(string $featuresDir, string $ns): iterable
    {
        // Scan all the test cases
        foreach (ClassLocator::loadTestCases($featuresDir, $ns) as $class) {
            $namespace = \substr($class, 0, \strrpos($class, '\\'));
            $feature = new Feature(
                testClass: $class,
                testNamespace: $namespace,
                taskQueue: $namespace,
            );

            yield $feature => \array_filter(
                \get_declared_classes(),
                static fn(string $class): bool => \str_starts_with($class, "$namespace\\"),
            );
        }
    }
}
