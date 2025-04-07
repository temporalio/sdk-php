<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Workflow;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Temporal\Internal\Workflow\Logger;
use Temporal\Workflow;
use Temporal\Workflow\ScopedContextInterface;

#[CoversClass(Logger::class)]
final class LoggerTestCase extends TestCase
{
    private LoggerInterface&MockObject $decoratedLogger;
    private Logger $logger;
    private ScopedContextInterface&MockObject $scopedContext;

    public function testAddsTaskQueueToContext(): void
    {
        $this->setReplayState(false);

        $taskQueue = 'test-queue';
        $this->logger = new Logger($this->decoratedLogger, true, $taskQueue);

        $this->decoratedLogger->expects($this->once())
            ->method('log')
            ->with(
                LogLevel::INFO,
                'Test message',
                ['task_queue' => $taskQueue, 'test' => 'value'],
            );

        $this->logger->log(LogLevel::INFO, 'Test message', ['test' => 'value']);
    }

    public function testSkipsLoggingDuringReplayWhenNotEnabled(): void
    {
        $this->setReplayState(true);

        $this->logger = new Logger($this->decoratedLogger, false, 'test-queue');

        $this->decoratedLogger->expects($this->never())->method('log');

        $this->logger->log(LogLevel::INFO, 'This message should be skipped');
    }

    public function testLogsInReplayWhenEnabled(): void
    {
        $this->setReplayState(true);

        $this->logger = new Logger($this->decoratedLogger, true, 'test-queue');

        $this->decoratedLogger->expects($this->once())
            ->method('log')
            ->with(LogLevel::INFO, 'This message should be logged');

        $this->logger->log(LogLevel::INFO, 'This message should be logged');
    }

    public function testNotInReplayAlwaysLogs(): void
    {
        $this->setReplayState(false);

        // loggingInReplay is false, but since we're not replaying, it should log anyway
        $this->logger = new Logger($this->decoratedLogger, false, 'test-queue');

        $this->decoratedLogger->expects($this->once())
            ->method('log')
            ->with(LogLevel::INFO, 'This message should be logged');

        $this->logger->log(LogLevel::INFO, 'This message should be logged');
    }

    public function testLogLevelMethods(): void
    {
        $this->setReplayState(false);
        $this->logger = new Logger($this->decoratedLogger, true, 'test-queue');

        $logLevels = [
            'emergency', 'alert', 'critical', 'error',
            'warning', 'notice', 'info', 'debug',
        ];

        foreach ($logLevels as $level) {
            $this->decoratedLogger->expects($this->once())
                ->method($level)
                ->with("Test $level message", ['task_queue' => 'test-queue']);

            $this->logger->$level("Test $level message");
        }
    }

    public function testAcceptsStringableObject(): void
    {
        $this->setReplayState(false);
        $this->logger = new Logger($this->decoratedLogger, true, 'test-queue');

        $stringable = new class implements \Stringable {
            public function __toString(): string
            {
                return 'Stringable message';
            }
        };

        $this->decoratedLogger->expects($this->once())
            ->method('info')
            ->with($stringable, ['task_queue' => 'test-queue']);

        $this->logger->info($stringable);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->decoratedLogger = $this->createMock(LoggerInterface::class);
        $this->scopedContext = $this->createMock(ScopedContextInterface::class);
        Workflow::setCurrentContext($this->scopedContext);
    }

    protected function tearDown(): void
    {
        Workflow::setCurrentContext(null);
        parent::tearDown();
    }

    /**
     * Helper method to set the replay state in the workflow context
     */
    private function setReplayState(bool $isReplaying): void
    {
        $this->scopedContext->method('isReplaying')
            ->willReturn($isReplaying);

        // Set the mocked context as the current Workflow context
        $reflectionClass = new \ReflectionClass(Workflow::class);
        $method = $reflectionClass->getMethod('setCurrentContext');
        $method->invoke(null, $this->scopedContext);
    }
}
