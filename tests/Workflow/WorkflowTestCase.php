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

        $this->queue = new TestingQueue();

        $this->services = new ServiceContainer(
            new TestingLoop(),
            new TestingClient($this->queue),
            new AttributeReader()
        );

        $this->services->env = new TestingEnvironment();
        $this->services->marshaller = new TestingMarshaller();
    }

    /**
     * @param RequestInterface $request
     * @param mixed|null $response
     * @return SuccessResponseInterface
     */
    protected function successResponseAndNext(RequestInterface $request, $response = null): SuccessResponseInterface
    {
        try {
            return $this->services->client->success($request, $response);
        } finally {
            $this->services->loop->tick();
        }
    }

    /**
     * @param RequestInterface $request
     * @param \Throwable $error
     * @return ErrorResponseInterface
     */
    protected function errorResponseAndNext(RequestInterface $request, \Throwable $error): ErrorResponseInterface
    {
        try {
            return $this->services->client->error($request, $error);
        } finally {
            $this->services->loop->tick();
        }
    }

    /**
     * @param object $context
     * @return array
     * @throws \ReflectionException
     */
    protected function marshal(object $context): array
    {
        return $this->services->marshaller->marshal($context);
    }
}
