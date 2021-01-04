<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Workflow;

use Spiral\Attributes\AttributeReader;
use Temporal\DataConverter\DataConverter;
use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Internal\Declaration\WorkflowInstance;
use Temporal\Internal\Marshaller\Mapper\AttributeMapperFactory;
use Temporal\Internal\ServiceContainer;
use Temporal\Internal\Workflow\Input;
use Temporal\Internal\Workflow\Process\Process;
use Temporal\Worker\Command\ErrorResponseInterface;
use Temporal\Worker\Command\RequestInterface;
use Temporal\Worker\Command\SuccessResponseInterface;
use Temporal\Workflow\WorkflowInfo;
use Temporal\Tests\TestCase;
use Temporal\Tests\Testing\TestingClient;
use Temporal\Tests\Testing\TestingEnvironment;
use Temporal\Tests\Testing\TestingLoop;
use Temporal\Tests\Testing\TestingMarshaller;
use Temporal\Tests\Testing\TestingQueue;

abstract class WorkflowTestCase extends TestCase
{
    /**
     * @var TestingQueue
     */
    protected TestingQueue $queue;

    /**
     * @var TestingLoop
     */
    protected TestingLoop $loop;

    /**
     * @var TestingClient
     */
    protected TestingClient $client;

    /**
     * @var TestingEnvironment
     */
    protected TestingEnvironment $env;

    /**
     * @var TestingMarshaller
     */
    protected TestingMarshaller $marshaller;

    /**
     * @var ServiceContainer
     */
    protected ServiceContainer $services;

    /**
     * @return void
     * @noinspection PhpImmutablePropertyIsWrittenInspection
     */
    protected function setUp(): void
    {
        parent::setUp();

        $reader = new AttributeReader();

        $this->queue = new TestingQueue();
        $this->loop = new TestingLoop();
        $this->client = new TestingClient($this->loop, $this->queue);
        $this->env = new TestingEnvironment();
        $this->marshaller = new TestingMarshaller(new AttributeMapperFactory($reader));

        $this->services = new ServiceContainer(
            $this->loop,
            $this->client,
            $reader,
            $this->queue,
            DataConverter::createDefault()
        );

        $this->services->env = $this->env;
        $this->services->marshaller = $this->marshaller;
    }


    /**
     * @param string $class
     * @param string $fun
     * @param WorkflowInfo|null $info
     * @param array $args
     * @return Process
     * @throws \ReflectionException
     */
    protected function createProcess(string $class, string $fun, WorkflowInfo $info = null, array $args = []): Process
    {
        $input = new Input($info, $args);

        return new Process($input, $this->services, $this->createInstance($class, $fun));
    }

    /**
     * @param string $class
     * @param string $function
     * @return WorkflowInstance
     * @throws \ReflectionException
     */
    protected function createInstance(string $class, string $function): WorkflowInstance
    {
        $reflectionClass = new \ReflectionClass($class);

        $reflectionFunction = $reflectionClass->getMethod($function);

        $prototype = new WorkflowPrototype($reflectionFunction->getName(), $reflectionFunction, $reflectionClass);

        return new WorkflowInstance($prototype, DataConverter::createDefault(), new $class());
    }

    /**
     * @param RequestInterface $request
     * @param mixed|null $response
     * @return SuccessResponseInterface
     */
    protected function successResponseAndNext(RequestInterface $request, $response = null): SuccessResponseInterface
    {
        try {
            return $this->client->success($request, $response);
        } finally {
            $this->loop->tick();
        }
    }

    /**
     * @return void
     */
    protected function tick(): void
    {
        $this->queue->clear();
        $this->loop->tick();
    }

    /**
     * @param RequestInterface $request
     * @param \Throwable $error
     * @return ErrorResponseInterface
     */
    protected function errorResponseAndNext(RequestInterface $request, \Throwable $error): ErrorResponseInterface
    {
        try {
            return $this->client->error($request, $error);
        } finally {
            $this->loop->tick();
        }
    }

    /**
     * @param object $context
     * @return array
     * @throws \ReflectionException
     */
    protected function marshal(object $context): array
    {
        return $this->marshaller->marshal($context);
    }
}
