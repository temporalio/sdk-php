<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Workflow;

use Psr\Log\LoggerInterface;
use Temporal\Workflow;

/**
 * A logger decorator for using in Workflows.
 *
 * @internal
 */
class Logger implements LoggerInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly bool $loggingInReplay,
        private readonly string $taskQueue,
    ) {}

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->shouldBeSkipped() or $this->logger->log($level, $message, $this->context($context));
    }

    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->shouldBeSkipped() or $this->logger->emergency($message, $this->context($context));
    }

    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->shouldBeSkipped() or $this->logger->alert($message, $this->context($context));
    }

    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->shouldBeSkipped() or $this->logger->critical($message, $this->context($context));
    }

    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->shouldBeSkipped() or $this->logger->error($message, $this->context($context));
    }

    public function warning(\Stringable|string $message, array $context = []): void
    {
        $this->shouldBeSkipped() or $this->logger->warning($message, $this->context($context));
    }

    public function notice(\Stringable|string $message, array $context = []): void
    {
        $this->shouldBeSkipped() or $this->logger->notice($message, $this->context($context));
    }

    public function info(\Stringable|string $message, array $context = []): void
    {
        $this->shouldBeSkipped() or $this->logger->info($message, $this->context($context));
    }

    public function debug(\Stringable|string $message, array $context = []): void
    {
        $this->shouldBeSkipped() or $this->logger->debug($message, $this->context($context));
    }

    private function context(array $source): array
    {
        return ['task_queue' => $this->taskQueue] + $source;
    }

    private function shouldBeSkipped(): bool
    {
        return Workflow::isReplaying() and !$this->loggingInReplay;
    }
}
