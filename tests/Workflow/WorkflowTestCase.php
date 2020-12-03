<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Client\Workflow;

use Temporal\Client\Internal\Declaration\Prototype\Collection;
use Temporal\Client\Internal\Marshaller\MarshallerInterface;
use Temporal\Client\Internal\Transport\ClientInterface;
use Temporal\Client\Internal\Workflow\Requests;
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
     * @var TestingLoop
     */
    protected TestingLoop $loop;

    /**
     * @var MarshallerInterface
     */
    protected MarshallerInterface $marshaller;

    /**
     * @var ClientInterface
     */
    protected ClientInterface $client;

    /**
     * @var TestingQueue
     */
    protected TestingQueue $queue;

    /**
     * @var TestingEnvironment
     */
    protected TestingEnvironment $env;

    /**
     * @var Requests
     */
    protected Requests $requests;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->loop = new TestingLoop();
        $this->queue = new TestingQueue();
        $this->env = new TestingEnvironment();
        $this->marshaller = new TestingMarshaller();
        $this->client = new TestingClient($this->queue);
        $this->requests = new Requests($this->marshaller, $this->env, $this->client, new Collection());
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
