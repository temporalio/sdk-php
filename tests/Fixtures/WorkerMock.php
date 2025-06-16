<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Fixtures;

use Temporal\Api\Failure\V1\Failure;
use Temporal\DataConverter\DataConverter;
use Temporal\Exception\Failure\FailureConverter;
use Temporal\Tests\TestCase;
use Temporal\Worker\Transport\HostConnectionInterface;
use Temporal\Worker\WorkerFactoryInterface;
use Temporal\WorkerFactory;
use Temporal\Worker\Transport\CommandBatch;

class WorkerMock implements HostConnectionInterface
{
    private WorkerFactoryInterface $factory;

    private array $in;

    private array $out;

    private int $indexIn;

    private int $indexOut;

    private bool $debug;

    private TestCase $testCase;

    public static function createMock(): WorkerMock
    {
        $_SERVER['RR_CODEC'] = 'json';

        $mock = new self();

        $mock->factory = WorkerFactory::create();
        $mock->registerWorkflowAndActivities();

        CommandResetter::reset();

        return $mock;
    }

    public function registerWorkflowAndActivities(): void
    {
        $taskQueue = $this->factory->newWorker('default');

        foreach ($this->getClasses(__DIR__ . '/src/Workflow') as $name) {
            $class = 'Temporal\\Tests\\Workflow\\' . $name;
            if (\class_exists($class)) {
                $taskQueue->registerWorkflowTypes($class);
            }
        }

        // register all activity
        foreach ($this->getClasses(__DIR__ . '/src/Activity') as $name) {
            $class = '\\Temporal\\Tests\\Activity\\' . $name;
            if (\class_exists($class)) {
                $taskQueue->registerActivityImplementations(new $class());
            }
        }
    }

    public function run(TestCase $testCase, array $queue, bool $debug = false): void
    {
        $this->debug = $debug;

        $this->in = $queue[1];
        $this->indexIn = 0;

        $this->out = $queue[0];
        $this->indexOut = 0;

        $this->testCase = $testCase;

        $this->factory->run($this);
    }

    public function waitBatch(): ?CommandBatch
    {
        if (!isset($this->out[$this->indexOut])) {
            return null;
        }

        $pair = $this->out[$this->indexOut];
        $this->indexOut++;

        if ($this->debug) {
            dump($pair[0]);
        }

        return new CommandBatch(
            $pair[0],
            \json_decode($pair[1], true),
        );
    }

    public function send(string $frame): void
    {
        $pair = $this->in[$this->indexIn];

        if ($this->debug) {
            dump($frame);
        }

        if ($pair[0] !== $frame) {
            // Parse error if exists
            $json = \json_decode($frame, true);
            if (\is_array($json)) {
                foreach ($json as $part) {
                    if (isset($part['failure'])) {
                        $failure = new Failure();
                        try {
                            $failure->mergeFromString(\base64_decode($part['failure']));
                        } catch (\Throwable) {
                            continue;
                        }
                        throw FailureConverter::mapFailureToException($failure, DataConverter::createDefault());
                    }
                }
            }
        }

        $this->testCase->assertEquals($pair[0], $frame);

        $this->indexIn++;
    }

    /**
     * @throws \Throwable
     */
    public function error(\Throwable $error): void
    {
        throw $error;
    }

    private function getClasses(string $dir): iterable
    {
        $files = \glob($dir . '/*.php');

        foreach ($files as $file) {
            yield \substr(\basename($file), 0, -4);
        }
    }
}
