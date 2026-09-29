<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Lambda;

use Psr\Log\LoggerInterface;
use Temporal\Lambda\Exception\ConfigurationException;
use Temporal\Lambda\Process\Signal;
use Temporal\Lambda\Process\SignalTrap;
use Temporal\Lambda\RoadRunner\ConfigFile;
use Temporal\Lambda\RoadRunner\Process;
use Temporal\Worker\Logger\StderrLogger;

final class Runtime
{
    private const MINIMUM_RUN_MS = 1_000;

    private ?Process $roadRunner = null;
    private string $requestId = '-';

    public function __construct(
        private readonly Config $config,
        private readonly RuntimeApi $api,
        private readonly LoggerInterface $logger,
    ) {}

    public static function create(
        ?Config $config = null,
        ?RuntimeApi $api = null,
        ?LoggerInterface $logger = null,
    ): self {
        $config ??= Environment::capture();

        return new self(
            $config,
            $api ?? new RuntimeApi($config->runtimeApi),
            $logger ?? new StderrLogger(),
        );
    }

    public static function main(): never
    {
        $logger = new StderrLogger();

        try {
            self::create(logger: $logger)->run();
        } catch (ConfigurationException $e) {
            $logger->error('configuration error: ' . $e->getMessage());
            self::reportInitError($e);

            exit(1);
        } catch (\Throwable $e) {
            $logger->error('runtime loop crashed: ' . $e->getMessage());

            exit(1);
        }

        exit(0);
    }

    public function run(): void
    {
        ConfigFile::render($this->config);
        $this->trapSignals();
        \register_shutdown_function(fn() => $this->roadRunner?->stop());

        $this->log('info', \sprintf(
            'runtime ready: buffer=%dms graceful=%dms config=%s',
            $this->config->shutdownBufferMs,
            $this->config->gracefulTimeoutMs,
            $this->config->roadRunnerConfigTemplate,
        ));

        while (true) {
            $invocation = $this->api->nextInvocation();
            $this->requestId = $invocation->requestId;

            $error = $this->invoke($invocation->deadlineMs);
            if ($error !== null) {
                $this->log('error', 'invocation failed: ' . $error->getMessage());
            }

            $this->acknowledge($invocation->requestId, $error);
            $this->requestId = '-';
        }
    }

    private static function reportInitError(\Throwable $error): void
    {
        $runtimeApi = Environment::runtimeApi();
        if ($runtimeApi === null) {
            return;
        }

        (new RuntimeApi($runtimeApi))->reportInitError($error);
    }

    private function acknowledge(string $requestId, ?\Throwable $error): void
    {
        try {
            if ($error === null) {
                $this->api->respond($requestId);
            } else {
                $this->api->reportInvocationError($requestId, $error);
            }
        } catch (\Throwable $e) {
            $this->log('error', 'acknowledgement failed: ' . $e->getMessage());
        }
    }

    private function invoke(int $deadlineMs): ?\Throwable
    {
        $startedAt = Clock::nowMs();
        $stopAtMs = $deadlineMs - $this->config->shutdownBufferMs;
        $this->log('info', \sprintf('invocation started, remaining=%dms', $deadlineMs - $startedAt));

        try {
            $runMs = $stopAtMs - Clock::nowMs();
            if ($runMs < self::MINIMUM_RUN_MS) {
                throw new \RuntimeException(\sprintf(
                    'Insufficient invocation time: %dms left to poll after reserving a %dms shutdown buffer',
                    $runMs,
                    $this->config->shutdownBufferMs,
                ));
            }

            $this->roadRunner = new Process($this->config, $this->logger, $this->requestId);
            $this->roadRunner->start();
            $this->log('info', "roadrunner started, polling for {$runMs}ms");

            $this->pollUntil($stopAtMs);
            $this->log('info', 'shutdown window reached, stopping roadrunner');

            return null;
        } catch (\Throwable $e) {
            return $e;
        } finally {
            $this->roadRunner?->stop();
            $this->roadRunner = null;
            $this->log('info', \sprintf(
                'invocation finished in %dms, remaining=%dms',
                Clock::nowMs() - $startedAt,
                $deadlineMs - Clock::nowMs(),
            ));
        }
    }

    private function log(string $level, string $message): void
    {
        $this->logger->log($level, $message, ['requestId' => $this->requestId]);
    }

    private function pollUntil(int $stopAtMs): void
    {
        while (Clock::nowMs() < $stopAtMs) {
            $exitCode = $this->roadRunner?->exitCode();
            if ($exitCode !== null) {
                throw new \RuntimeException("RoadRunner exited before the shutdown window with code {$exitCode}");
            }

            \usleep(Process::POLL_INTERVAL_US);
        }
    }

    private function trapSignals(): void
    {
        SignalTrap::trap([Signal::SIGTERM, Signal::SIGINT], function (int $received): void {
            $this->log('error', "received signal {$received}, stopping roadrunner");

            exit(128 + $received);
        });
    }
}
