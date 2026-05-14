<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App;

use Google\Protobuf\Timestamp;
use PHPUnit\Framework\SkippedTest;
use Psr\Log\LoggerInterface;
use Spiral\Core\Container;
use Spiral\Core\Scope;
use Temporal\Api\Enums\V1\EventType;
use Temporal\Api\Failure\V1\Failure;
use Temporal\Client\ClientOptions;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Exception\TemporalException;
use Temporal\Plugin\ClientPluginInterface;
use Temporal\Plugin\PluginRegistry;
use Temporal\Tests\Acceptance\App\Attribute\Worker;
use Temporal\Tests\Acceptance\App\Feature\WorkerFactory;
use Temporal\Tests\Acceptance\App\Logger\ClientLogger;
use Temporal\Tests\Acceptance\App\Logger\LoggerFactory;
use Temporal\Tests\Acceptance\App\Logger\TranscriptLine;
use Temporal\Tests\Acceptance\App\Logger\TranscriptSection;
use Temporal\Tests\Acceptance\App\Logger\TranscriptStore;
use Temporal\Tests\Acceptance\App\Logger\TranscriptWriter;
use Temporal\Tests\Acceptance\App\Runtime\ContainerFacade;
use Temporal\Tests\Acceptance\App\Runtime\Feature;
use Temporal\Tests\Acceptance\App\Runtime\RRStarter;
use Temporal\Tests\Acceptance\App\Runtime\State;
use Temporal\Tests\Acceptance\App\Runtime\TemporalStarter;
use Temporal\Worker\Logger\StderrLogger;

abstract class TestCase extends \Temporal\Tests\TestCase
{
    private const TRANSCRIPT_FLUSH_USLEEP = 500_000;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var State $state */
        $state = ContainerFacade::$container->get(State::class);
        $state->countFeatures() === 0 and RuntimeBuilder::hydrateClasses($state);
    }

    #[\Override]
    protected function runTest(): mixed
    {
        $container = ContainerFacade::$container;
        /** @var State $runtime */
        $runtime = $container->get(State::class);
        $feature = $runtime->getFeatureByTestCase(static::class);

        // Configure client logger
        $logger = LoggerFactory::createClientLogger($feature->taskQueue);
        $logger->clear();

        // Build scope bindings
        $bindings = [
            Feature::class => $feature,
            static::class => $this,
            State::class => $runtime,
            LoggerInterface::class => ClientLogger::class,
            ClientLogger::class => $logger,
        ];

        // Auto-inject plugin-configured client from #[Worker(plugins: [...])] attribute
        $workerAttr = WorkerFactory::findAttribute(static::class);
        if ($workerAttr?->plugins !== null) {
            $pluginRegistry = new PluginRegistry($workerAttr->plugins);
            $clientPlugins = $pluginRegistry->getPlugins(ClientPluginInterface::class);
            if ($clientPlugins !== []) {
                $existingClient = $container->get(WorkflowClientInterface::class);
                $pluginClient = WorkflowClient::create(
                    serviceClient: $existingClient->getServiceClient(),
                    options: (new ClientOptions())->withNamespace($runtime->namespace),
                    pluginRegistry: new PluginRegistry($workerAttr->plugins),
                );
                $bindings[WorkflowClientInterface::class] = $pluginClient;
            }
        }

        return $container->runScope(
            new Scope(name: 'feature', bindings: $bindings),
            function (Container $container): mixed {
                $args = [];
                $caughtException = null;
                $startedAt = \microtime(true);

                $transcript = $container->has(TranscriptWriter::class)
                    ? $container->get(TranscriptWriter::class)
                    : null;
                $transcript?->writeTestBoundary(TranscriptSection::TEST_START, [
                    'class' => static::class,
                    'method' => $this->name(),
                ]);

                try {
                    $reflection = new \ReflectionMethod($this, $this->name());
                    $args = $container->resolveArguments($reflection);
                    $this->setDependencyInput($args);

                    return parent::runTest();
                } catch (\Throwable $e) {
                    $caughtException = $e;
                    if ($e instanceof TemporalException) {
                        echo \sprintf(
                            "\n=== En error occurred while testing %s: %s (%s) ===\n",
                            static::class . '::' . $this->name(),
                            $e->getMessage(),
                            $e::class,
                        );
                        echo "\n=== Stack trace ===\n";
                        echo $e->getTraceAsString();
                        echo "\n=== Workflow history ===\n";
                        $this->printWorkflowHistory($container->get(WorkflowClientInterface::class), $args);

                        $logRecords = $container->get(ClientLogger::class)->getRecords();
                        if ($logRecords !== []) {
                            echo "\n=== Client log records ===\n";
                            foreach ($logRecords as $record) {
                                echo \sprintf(
                                    "[%s] %s%s\n",
                                    $record->level,
                                    $record->message,
                                    \json_encode($record->context, JSON_UNESCAPED_UNICODE),
                                );
                            }
                        }

                        echo "\n\n";
                    }

                    if (!$e instanceof SkippedTest) {
                        // Restart RR if a Error occurs
                        $roadRunnerStarter = $container->get(RRStarter::class);
                        $roadRunnerStarter->stop();
                        $roadRunnerStarter->start();
                    }

                    throw $e;
                } finally {
                    if ($transcript !== null) {
                        $this->dumpHistoryToTranscript(
                            $transcript,
                            $container->get(WorkflowClientInterface::class),
                            $args,
                            $caughtException,
                        );
                    }
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
                    if ($transcript !== null) {
                        $status = $caughtException === null
                            ? 'passed'
                            : ($caughtException instanceof SkippedTest ? 'skipped' : 'failed');
                        $endAttributes = [
                            'class' => static::class,
                            'method' => $this->name(),
                            'status' => $status,
                            'duration_ms' => (int) ((\microtime(true) - $startedAt) * 1000),
                        ];
                        if ($caughtException !== null) {
                            $endAttributes['exception_class'] = $caughtException::class;
                        }
                        $transcript->writeTestBoundary(TranscriptSection::TEST_END, $endAttributes);
                        $transcript->flush();
                        if ($caughtException !== null && !$caughtException instanceof SkippedTest) {
                            $stderr = $container->has(StderrLogger::class)
                                ? $container->get(StderrLogger::class)
                                : null;
                            $stderr?->error('transcript', ['path' => $transcript->getPath()]);
                            $stderr?->info('run `composer transcripts:last` to view the merged stream');
                        }
                    }
                }
            },
        );
    }

    /**
     * @return list<TranscriptLine>
     */
    protected function readCurrentTestTranscript(): array
    {
        \usleep(self::TRANSCRIPT_FLUSH_USLEEP);
        $run = TranscriptStore::create()->currentRun();
        if ($run === null) {
            return [];
        }
        return $run->reader()->linesForTest(static::class, $this->name());
    }

    private function dumpHistoryToTranscript(
        TranscriptWriter $transcript,
        WorkflowClientInterface $workflowClient,
        array $args,
        ?\Throwable $exception,
    ): void {
        $executions = [];
        foreach ($args as $arg) {
            if ($arg instanceof WorkflowStubInterface) {
                $execution = $arg->getExecution();
                $executions[$execution->getID()] = $execution;
            }
        }
        if ($executions === []) {
            $transcript->writeMeta('history_skipped', ['reason' => 'no_executions_inspected']);
            return;
        }
        foreach ($executions as $execution) {
            try {
                $eventCount = 0;
                foreach ($workflowClient->getWorkflowHistory($execution) as $event) {
                    $eventCount++;
                    $eventAttributes = [
                        'event_id' => (int) $event->getEventId(),
                        'event_type' => EventType::name($event->getEventType()),
                    ];
                    $eventTime = $event->getEventTime();
                    if ($eventTime !== null) {
                        $eventAttributes['event_time'] = $eventTime->getSeconds() . '.' . $eventTime->getNanos();
                    }
                    $payloadJson = '{}';
                    try {
                        $payloadJson = $event->serializeToJsonString();
                    } catch (\Throwable $serializationError) {
                        $eventAttributes['serialize_error'] = $serializationError->getMessage();
                    }
                    $transcript->writeHistoryEvent(
                        $execution->getID(),
                        $execution->getRunID(),
                        $eventAttributes,
                        $payloadJson,
                    );
                }
                $transcript->writeMeta('history_dumped', [
                    'workflow_id' => $execution->getID(),
                    'run_id' => $execution->getRunID(),
                    'event_count' => $eventCount,
                ]);
            } catch (\Throwable $historyError) {
                $transcript->writeHistoryError($execution->getID(), $historyError);
            }
        }
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

            foreach ($workflowClient->getWorkflowHistory($arg->getExecution()) as $event) {
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
