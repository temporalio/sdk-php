<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Client\Workflow;

use Spiral\Attributes\AttributeReader;
use Temporal\Client\Internal\Marshaller\Mapper\AttributeMapperFactory;
use Temporal\Client\Internal\ServiceContainer;
use Temporal\Client\Worker\Command\ErrorResponseInterface;
use Temporal\Client\Worker\Command\RequestInterface;
use Temporal\Client\Worker\Command\SuccessResponseInterface;
use Temporal\Tests\Client\TestCase;
use Temporal\Tests\Client\Testing\TestingClient;
use Temporal\Tests\Client\Testing\TestingEnvironment;
use Temporal\Tests\Client\Testing\TestingLoop;
use Temporal\Tests\Client\Testing\TestingMarshaller;
use Temporal\Tests\Client\Testing\TestingQueue;

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

        $this->services = new ServiceContainer($this->loop, $this->client, $reader, $this->queue);
        $this->services->env = $this->env;
        $this->services->marshaller = $this->marshaller;
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
