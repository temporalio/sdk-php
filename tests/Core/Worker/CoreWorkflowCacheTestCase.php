<?php

declare(strict_types=1);

namespace Temporal\Tests\Core\Worker;

use PHPUnit\Framework\TestCase;
use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowOptions;
use Temporal\Tests\Core\DevServer;

final class CoreWorkflowCacheTestCase extends TestCase
{
    private const MEMORY_LIMIT = '64M';
    private const MEMORY_LIMIT_BYTES = 64 * 1024 * 1024;
    private const WORKFLOWS = 1300;
    private const WORKFLOW_TIMEOUT_SECONDS = 120;
    private const SAFETY_TIMEOUT_SECONDS = 90;

    public function testDefaultCacheKeepsTheWorkerInsideItsMemoryLimit(): void
    {
        $address = DevServer::address();
        $queue = \uniqid('core-cache-', true);
        $client = WorkflowClient::create(ServiceClient::create($address));
        for ($i = 0; $i < self::WORKFLOWS; ++$i) {
            $client->start($client->newUntypedWorkflowStub('CoreCachedWorkflow', WorkflowOptions::new()
                ->withTaskQueue($queue)
                ->withWorkflowExecutionTimeout(self::WORKFLOW_TIMEOUT_SECONDS)));
        }

        [$code, $output] = self::runWorker($address, $queue);

        self::assertSame(0, $code, $output);
        self::assertSame(1, \preg_match('/^started (\d+), peak (\d+)$/m', $output, $result), $output);
        self::assertSame(self::WORKFLOWS, (int) $result[1]);
        self::assertLessThan(self::MEMORY_LIMIT_BYTES, (int) $result[2]);
    }

    /**
     * @return array{int, string}
     */
    private static function runWorker(string $address, string $queue): array
    {
        $script = \tempnam(\sys_get_temp_dir(), 'core-cache-worker');
        \file_put_contents($script, self::workerScript());
        try {
            $process = \proc_open(
                [\PHP_BINARY, '-d', 'memory_limit=' . self::MEMORY_LIMIT, $script, $address, $queue, (string) self::WORKFLOWS, (string) self::SAFETY_TIMEOUT_SECONDS],
                [1 => ['pipe', 'w'], 2 => ['redirect', 1]],
                $pipes,
            );
            self::assertIsResource($process);
            $output = (string) \stream_get_contents($pipes[1]);

            return [\proc_close($process), $output];
        } finally {
            \unlink($script);
        }
    }

    private static function workerScript(): string
    {
        $autoload = \var_export(\dirname(__DIR__, 3) . '/vendor/autoload.php', true);

        return <<<PHP
            <?php

            declare(strict_types=1);

            require {$autoload};

            use Temporal\\Worker\\Core\\CoreWorkerFactory;
            use Temporal\\Workflow;
            use Temporal\\Workflow\\WorkflowInterface;
            use Temporal\\Workflow\\WorkflowMethod;

            #[WorkflowInterface]
            final class CoreCachedWorkflow
            {
                public static int \$started = 0;

                #[WorkflowMethod(name: 'CoreCachedWorkflow')]
                public function run(): \\Generator
                {
                    if (!Workflow::isReplaying() && ++self::\$started === (int) \$GLOBALS['argv'][3]) {
                        \\posix_kill(\\getmypid(), \\SIGTERM);
                    }

                    yield Workflow::await(static fn(): bool => false);
                }
            }

            \$factory = CoreWorkerFactory::create(address: \$argv[1]);
            \$factory->newWorker(\$argv[2])->registerWorkflowTypes(CoreCachedWorkflow::class);
            \\pcntl_signal(\\SIGALRM, static fn() => \\posix_kill(\\getmypid(), \\SIGTERM));
            \\pcntl_alarm((int) \$argv[4]);
            \$code = \$factory->run();
            echo 'started ', CoreCachedWorkflow::\$started, ', peak ', \\memory_get_peak_usage(), \\PHP_EOL;
            exit(\$code);
            PHP;
    }
}
