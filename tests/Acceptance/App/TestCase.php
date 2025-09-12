<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App;

use Google\Protobuf\Timestamp;
use Psr\Log\LoggerInterface;
use Spiral\Core\Container;
use Spiral\Core\Scope;
use Temporal\Api\Enums\V1\EventType;
use Temporal\Api\Failure\V1\Failure;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Exception\TemporalException;
use Temporal\Tests\Acceptance\App\Logger\ClientLogger;
use Temporal\Tests\Acceptance\App\Logger\LoggerFactory;
use Temporal\Tests\Acceptance\App\Runtime\ContainerFacade;
use Temporal\Tests\Acceptance\App\Runtime\Feature;
use Temporal\Tests\Acceptance\App\Runtime\RRStarter;
use Temporal\Tests\Acceptance\App\Runtime\State;

abstract class TestCase extends \Temporal\Tests\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        /** @var State $state */
        $state = ContainerFacade::$container->get(State::class);
        $state->countFeatures() === 0 and RuntimeBuilder::hydrateClasses($state);
    }

    protected function runTest(): mixed
    {
        $c = ContainerFacade::$container;
        /** @var State $runtime */
        $runtime = $c->get(State::class);
        $feature = $runtime->getFeatureByTestCase(static::class);

        // Configure client logger
        $logger = LoggerFactory::createClientLogger($feature->taskQueue);
        $logger->clear();

        return $c->runScope(
            new Scope(name: 'feature', bindings: [
                Feature::class => $feature,
                static::class => $this,
                State::class => $runtime,
                LoggerInterface::class => ClientLogger::class,
                ClientLogger::class => $logger,
            ]),
            function (Container $container): mixed {
                $reflection = new \ReflectionMethod($this, $this->name());
                $args = $container->resolveArguments($reflection);
                $this->setDependencyInput($args);

                try {
                    return parent::runTest();
                } catch (\Throwable $e) {
                    // Restart RR if a Error occurs
                    /** @var RRStarter $runner */
                    $runner = $container->get(RRStarter::class);
                    $runner->stop();
                    $runner->start();

                    if ($e instanceof TemporalException) {
                        echo "\n=== Workflow history for failed test {$this->name()} ===\n";
                        $this->printWorkflowHistory($container->get(WorkflowClientInterface::class), $args);
                    }

                    throw $e;
                } finally {
                    // Cleanup: terminate injected workflow if any
                    foreach ($args as $arg) {
                        if ($arg instanceof WorkflowStubInterface) {
                            try {
                                $arg->terminate('test-end');
                            } catch (\Throwable $e) {
                                // ignore
                            }
                        }
                    }
                }
            },
        );
    }

    private function printWorkflowHistory(WorkflowClientInterface $workflowClient, array $args): void
    {
        foreach ($args as $arg) {
            if (!$arg instanceof WorkflowStubInterface) {
                continue;
            }

            $fnTime = static fn(?Timestamp $ts): float => $ts === null
                ? 0
                : $ts->getSeconds() + \round($ts->getNanos() / 1_000_000_000, 6);

            foreach ($workflowClient->getWorkflowHistory(
                $arg->getExecution(),
            ) as $event) {
                $start ??= $fnTime($event->getEventTime());
                echo "\n" . \str_pad((string) $event->getEventId(), 3, ' ', STR_PAD_LEFT) . ' ';
                # Calculate delta time
                $deltaMs = \round(1_000 * ($fnTime($event->getEventTime()) - $start));
                echo \str_pad(\number_format($deltaMs, 0, '.', "'"), 6, ' ', STR_PAD_LEFT) . 'ms  ';
                echo \str_pad(EventType::name($event->getEventType()), 40, ' ', STR_PAD_RIGHT) . ' ';

                $cause = $event->getStartChildWorkflowExecutionFailedEventAttributes()?->getCause()
                    ?? $event->getSignalExternalWorkflowExecutionFailedEventAttributes()?->getCause()
                    ?? $event->getRequestCancelExternalWorkflowExecutionFailedEventAttributes()?->getCause();
                if ($cause !== null) {
                    echo "Cause: $cause";
                    continue;
                }

                $failure = $event->getActivityTaskFailedEventAttributes()?->getFailure()
                    ?? $event->getWorkflowTaskFailedEventAttributes()?->getFailure()
                    ?? $event->getNexusOperationFailedEventAttributes()?->getFailure()
                    ?? $event->getWorkflowExecutionFailedEventAttributes()?->getFailure()
                    ?? $event->getChildWorkflowExecutionFailedEventAttributes()?->getFailure()
                    ?? $event->getNexusOperationCancelRequestFailedEventAttributes()?->getFailure();

                if ($failure === null) {
                    continue;
                }

                # Render failure
                echo "Failure:\n";
                echo "    ========== BEGIN ===========\n";
                $this->renderFailure($failure, 1);
                echo "    =========== END ============";
            }
        }
    }

    private function renderFailure(Failure $failure, int $level): void
    {
        $fnPad = static function (string $str) use ($level): string {
            $pad = \str_repeat('    ', $level);
            return $pad . \str_replace("\n", "\n$pad", $str);
        };
        echo $fnPad('Source: ' . $failure->getSource()) . "\n";
        echo $fnPad('Info: ' . $failure->getFailureInfo()) . "\n";
        echo $fnPad('Message: ' . $failure->getMessage()) . "\n";
        echo $fnPad("Stack trace:") . "\n";
        echo $fnPad($failure->getStackTrace()) . "\n";
        $previous = $failure->getCause();
        if ($previous !== null) {
            echo $fnPad('————————————————————————————') . "\n";
            echo $fnPad('Caused by:') . "\n";
            $this->renderFailure($previous, $level + 1);
        }
    }
}
