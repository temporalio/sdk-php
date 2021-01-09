<?php

namespace Temporal\Tests\Fixtures;

use Temporal\DataConverter\DataConverter;
use Temporal\Tests\TestCase;
use Temporal\Worker;
use Temporal\Worker\Transport\Batch;

class WorkerMock implements Worker\Transport\RelayConnectionInterface
{
    private Worker $worker;

    /** @var array */
    private array $in;

    /** @var array */
    private array $out;

    /** @var int */
    private int $indexIn;

    /** @var int */
    private int $indexOut;

    /** @var bool */
    private bool $debug;

    private TestCase $testCase;

    public static function createMock(): WorkerMock
    {
        $_SERVER['RR_CODEC'] = 'json';

        $mock = new self();

        $worker = new Worker(
            DataConverter::createDefault(),
            $mock,
        );

        $mock->worker = $worker;
        $mock->registerWorkflowAndActivities();

        CommandResetter::reset();

        return $mock;
    }

    public function registerWorkflowAndActivities()
    {
        $taskQueue = $this->worker->createAndRegister('default');

        foreach ($this->getClasses(__DIR__ . '/src/Workflow') as $name) {
            $taskQueue->addWorkflow('Temporal\\Tests\\Workflow\\' . $name);
        }

        // register all activity
        foreach ($this->getClasses(__DIR__ . '/src/Activity') as $name) {
            $taskQueue->addActivity('Temporal\\Tests\\Activity\\' . $name);
        }
    }

    /**
     * @param TestCase $testCase
     * @param array $queue
     * @param bool $debug
     */
    public function run(TestCase $testCase, array $queue, bool $debug = false)
    {
        $this->debug = $debug;

        $this->in = $queue[1];
        $this->indexIn = 0;

        $this->out = $queue[0];
        $this->indexOut = 0;

        $this->testCase = $testCase;

        $this->worker->run();
    }

    /**
     * @return Batch|null
     */
    public function await(): ?Batch
    {
        if (!isset($this->out[$this->indexOut])) {
            return null;
        }

        $pair = $this->out[$this->indexOut];
        $this->indexOut++;

        if ($this->debug) {
            dump($pair[0]);
        }

        return new Batch(
            $pair[0],
            json_decode($pair[1], true),
        );
    }

    /**
     * @param string $frame
     */
    public function send(string $frame): void
    {
        $pair = $this->in[$this->indexIn];

        if ($this->debug) {
            dump($frame);
        }

        $this->testCase->assertEquals($pair[0], $frame);

        $this->indexIn++;
    }

    /**
     * @param \Throwable $error
     */
    public function error(\Throwable $error): void
    {
        throw $error;
    }

    private function getClasses(string $dir): iterable
    {
        $files = glob($dir . '/*.php');

        foreach ($files as $file) {
            yield substr(basename($file), 0, -4);
        }
    }
}
