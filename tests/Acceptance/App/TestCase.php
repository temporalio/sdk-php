<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App;

use PHPUnit\Framework\SkippedTest;
use Psr\Log\LoggerInterface;
use Spiral\Core\Container;
use Spiral\Core\Scope;
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
use Temporal\Testing\Transcript\TranscriptLine;
use Temporal\Testing\Transcript\TranscriptSection;
use Temporal\Testing\Transcript\TranscriptStore;
use Temporal\Testing\Transcript\TranscriptWriter;
use Temporal\Testing\Transcript\WorkflowHistoryDumper;
use Temporal\Worker\Logger\StderrLogger;
use Temporal\Tests\Acceptance\App\Runtime\ContainerFacade;
use Temporal\Tests\Acceptance\App\Runtime\Feature;
use Temporal\Tests\Acceptance\App\Runtime\RRStarter;
use Temporal\Tests\Acceptance\App\Runtime\State;
use Temporal\Tests\Acceptance\App\Runtime\TemporalStarter;

abstract class TestCase extends \Temporal\Tests\TestCase
{
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
        $workflowClient = $container->get(WorkflowClientInterface::class);

        // Auto-inject plugin-configured client from #[Worker(plugins: [...])] attribute
        $workerAttr = WorkerFactory::findAttribute(static::class);
        if ($workerAttr?->plugins !== null) {
            $pluginRegistry = new PluginRegistry($workerAttr->plugins);
            $clientPlugins = $pluginRegistry->getPlugins(ClientPluginInterface::class);
            if ($clientPlugins !== []) {
                $pluginClient = WorkflowClient::create(
                    serviceClient: $workflowClient->getServiceClient(),
                    options: (new ClientOptions())->withNamespace($runtime->namespace),
                    pluginRegistry: new PluginRegistry($workerAttr->plugins),
                );
                $bindings[WorkflowClientInterface::class] = $pluginClient;
            }
        }

        return $container->runScope(
            new Scope(name: 'feature', bindings: $bindings),
            function (Container $container) use ($workflowClient): mixed {
                $args = [];
                $caughtException = null;
                $startedAt = \microtime(true);

                $transcript = $container->has(TranscriptWriter::class)
                    ? $container->get(TranscriptWriter::class)
                    : null;
                $dumper = $container->has(WorkflowHistoryDumper::class)
                    ? $container->get(WorkflowHistoryDumper::class)
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

                        if ($transcript !== null) {
                            $dumper?->renderForFailure($transcript, $workflowClient, $args);
                        }

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
                        $dumper?->dump($transcript, $workflowClient, $args);
                    }
                    foreach ($args as $arg) {
                        if ($arg instanceof WorkflowStubInterface) {
                            try {
                                $arg->terminate('test-end');
                            } catch (\Throwable $e) {
                                $transcript?->writeMeta('workflow_terminate_failed', [
                                    'workflow_id' => $arg->getExecution()->getID(),
                                    'class' => $e::class,
                                    'message' => $e->getMessage(),
                                ]);
                            }
                        }
                    }
                    if ($transcript !== null) {
                        $status = match (true) {
                            $caughtException === null => 'passed',
                            $caughtException instanceof SkippedTest => 'skipped',
                            default => 'failed',
                        };
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
                            $runId = TranscriptStore::currentRunIdFromEnvironment();
                            $store = TranscriptStore::create(stderr: $stderr);
                            $run = $runId === null ? $store->latestRun() : $store->findRun($runId);
                            if ($run !== null && $run->files() !== []) {
                                try {
                                    $mergedPath = $run->merge();
                                    $stderr?->info("view merged transcript: less {$mergedPath}");
                                    if (self::shouldDumpTranscriptOnFail()) {
                                        $content = @\file_get_contents($mergedPath);
                                        if (\is_string($content) && $content !== '') {
                                            $label = $runId !== null ? "transcript run {$runId}" : 'transcript';
                                            $stderr?->info("{$label} dump:\n" . $content);
                                        }
                                    }
                                } catch (\Throwable $mergeError) {
                                    $stderr?->warning('transcript merge failed', [
                                        'message' => $mergeError->getMessage(),
                                    ]);
                                }
                            }
                        }
                    }
                }
            },
        );
    }

    private static function shouldDumpTranscriptOnFail(): bool
    {
        $flag = \getenv('TEMPORAL_TRANSCRIPT_DUMP_ON_FAIL');
        return \is_string($flag) && !\in_array(\strtolower($flag), ['', '0', 'false', 'off', 'no'], true);
    }

    /**
     * @return list<TranscriptLine>
     */
    protected function readCurrentTestTranscript(): array
    {
        $run = TranscriptStore::create()->currentRun();
        if ($run === null) {
            return [];
        }
        return $run->reader()->linesForTest(static::class, $this->name());
    }

}
