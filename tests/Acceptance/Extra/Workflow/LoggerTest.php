<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\Logger;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\Logger\ClientLogger;
use Temporal\Tests\Acceptance\App\Runtime\Feature;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class LoggerTest extends TestCase
{
    #[Test]
    public function loggerBasicLogging(
        #[Stub('Logger_Test_Workflow')] WorkflowStubInterface $stub,
        ClientLogger $logger,
    ): void {
        // Send signal to complete the workflow
        $stub->signal('exit');

        // Execute workflow that logs a basic message
        $result = $stub->getResult();

        $this->assertTrue($result, 'Workflow completed successfully');

        // Check logs
        $records = $logger->getRecords();
        $this->assertCount(2, $records); // Start and completion logs

        $this->assertSame('info', $records[0]->level);
        $this->assertSame('Workflow execution started', $records[0]->message);

        $this->assertSame('info', $records[1]->level);
        $this->assertSame('Workflow completed', $records[1]->message);
    }

    #[Test]
    public function loggerWithContext(
        #[Stub('Logger_Test_Workflow')] WorkflowStubInterface $stub,
        ClientLogger $logger,
    ): void {
        // Execute query to log with context
        $result = $stub->query('logWithContext')->getValue(0);

        $this->assertSame('query executed', $result);

        // Check logs - not checking count as query might be called multiple times
        $records = $logger->getRecords();
        $hasExpectedLog = false;

        foreach ($records as $record) {
            if ($record->level === 'debug' && $record->message === 'Log message with context from query') {
                $hasExpectedLog = true;
                $this->assertArrayHasKey('key1', $record->context);
                $this->assertArrayHasKey('key2', $record->context);
                $this->assertSame('value1', $record->context['key1']);
                $this->assertSame(42, $record->context['key2']);
                break;
            }
        }

        $this->assertTrue($hasExpectedLog, 'Expected debug log with context not found');

        // Complete the workflow
        $stub->signal('exit');
        $stub->getResult();
    }

    #[Test]
    public function loggerMultipleLevels(
        #[Stub('Logger_Test_Workflow')] WorkflowStubInterface $stub,
        ClientLogger $logger,
        Feature $feature,
    ): void {
        // Execute update to log at multiple levels
        $updateResult = $stub->update('logMultipleLevels')->getValue(0);

        $this->assertSame('update completed', $updateResult);

        // Complete the workflow
        $stub->signal('exit');
        $stub->getResult();

        // Check logs
        $records = $logger->getRecords();

        // Extract update logs
        $updateLogs = [];
        foreach ($records as $record) {
            if (\str_contains($record->message, 'from update')) {
                $updateLogs[] = $record;
            }
        }

        $this->assertCount(5, $updateLogs, 'Expected 5 update logs');

        $expectedLevels = ['debug', 'info', 'notice', 'warning', 'error'];
        $expectedMessages = [
            'Debug message from update',
            'Info message from update',
            'Notice message from update',
            'Warning message from update',
            'Error message from update',
        ];

        foreach ($updateLogs as $index => $record) {
            $this->assertSame($expectedLevels[$index], $record->level);
            $this->assertSame($expectedMessages[$index], $record->message);
            $this->assertSame($feature->taskQueue, $record->context['task_queue']);
        }
    }

    #[Test]
    public function loggerDuringSignalProcessing(
        #[Stub('Logger_Test_Workflow')] WorkflowStubInterface $stub,
        ClientLogger $logger,
    ): void {
        // Send signal to trigger logging
        $stub->signal('logFromSignal', 'Signal triggered log');

        // Complete the workflow
        $stub->signal('exit');
        $stub->getResult();

        // Check logs
        $records = $logger->getRecords();

        // Verify signal log exists
        $hasSignalLog = false;
        foreach ($records as $record) {
            if ($record->level === 'warning' && $record->message === 'Signal triggered log') {
                $hasSignalLog = true;
                break;
            }
        }

        $this->assertTrue($hasSignalLog, 'Expected signal log not found');
    }

    #[Test]
    public function loggingInAllHandlers(
        #[Stub('Logger_Test_Workflow')] WorkflowStubInterface $stub,
        ClientLogger $logger,
    ): void {
        // Send signal
        $stub->signal('logFromSignal', 'Signal log message');

        // Execute query
        $queryResult = $stub->query('logWithContext')->getValue(0);
        $this->assertSame('query executed', $queryResult);

        // Execute update
        $updateResult = $stub->update('logMultipleLevels')->getValue(0);
        $this->assertSame('update completed', $updateResult);

        // Close workflow
        $stub->signal('exit');
        $result = $stub->getResult();

        $this->assertTrue($result, 'Workflow completed successfully');

        // Check logs
        $records = $logger->getRecords();

        // Verify the signal log exists
        $hasSignalLog = false;
        foreach ($records as $record) {
            if ($record->level === 'warning' && $record->message === 'Signal log message') {
                $hasSignalLog = true;
                break;
            }
        }
        $this->assertTrue($hasSignalLog, 'Expected signal log not found');

        // Verify update logs exist
        $updateLogCount = 0;
        foreach ($records as $record) {
            if (\strpos($record->message, 'from update') !== false) {
                $updateLogCount++;
            }
        }
        $this->assertSame(5, $updateLogCount, 'Expected 5 update logs');

        // Verify the exit log exists
        $hasExitLog = false;
        foreach ($records as $record) {
            if ($record->level === 'info' && $record->message === 'Workflow completed') {
                $hasExitLog = true;
                break;
            }
        }
        $this->assertTrue($hasExitLog, 'Expected workflow completion log not found');
    }
}

#[WorkflowInterface]
class TestWorkflow
{
    private bool $exit = false;

    #[WorkflowMethod(name: "Logger_Test_Workflow")]
    public function handle()
    {
        $logger = Workflow::getLogger();
        $logger->info('Workflow execution started');

        yield Workflow::await(fn(): bool => $this->exit);

        $logger->info('Workflow completed');

        return true;
    }

    #[Workflow\SignalMethod(name: 'logFromSignal')]
    public function logFromSignal(string $message): void
    {
        $logger = Workflow::getLogger();
        $logger->warning($message);
    }

    #[Workflow\SignalMethod(name: 'exit')]
    public function exit(): void
    {
        $this->exit = true;
    }

    #[Workflow\QueryMethod(name: 'logWithContext')]
    public function logWithContext()
    {
        $logger = Workflow::getLogger();
        $logger->debug('Log message with context from query', [
            'key1' => 'value1',
            'key2' => 42,
        ]);

        return 'query executed';
    }

    #[Workflow\UpdateMethod(name: 'logMultipleLevels')]
    public function logMultipleLevels()
    {
        $logger = Workflow::getLogger();

        $logger->debug('Debug message from update');
        $logger->info('Info message from update');
        $logger->notice('Notice message from update');
        $logger->warning('Warning message from update');
        $logger->error('Error message from update');

        return 'update completed';
    }
}
