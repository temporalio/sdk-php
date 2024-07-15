<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Feature\Testing;

use JetBrains\PhpStorm\Immutable;
use React\Promise\PromiseInterface;
use Temporal\Internal\Queue\QueueInterface;
use Temporal\Internal\Transport\Client;
use Temporal\Worker\LoopInterface;
use Temporal\Worker\Transport\Command\Client\Response;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Worker\Transport\Command\Server\FailureResponse;
use Temporal\Workflow\WorkflowContextInterface;

class TestingClient extends CapturedClient
{
    /**
     * @var TestingQueue
     */
    #[Immutable]
    public TestingQueue $queue;

    /**
     * @param QueueInterface|null $queue
     */
    public function __construct(LoopInterface $loop, QueueInterface $queue = null)
    {
        $this->queue = $queue ?? new TestingQueue();

        parent::__construct(new Client($this->queue));
    }

    /**
     * @param RequestInterface $request
     * @param mixed|null $payload
     * @return TestingSuccessResponse
     */
    public function success(RequestInterface $request, $payload = null): TestingSuccessResponse
    {
        $response = Response::createSuccess($payload, $request->getID());

        $this->parent->dispatch($response);

        return new TestingSuccessResponse($response);
    }

    /**
     * @param RequestInterface $request
     * @param \Throwable $error
     * @return TestingFailureResponse
     */
    public function error(RequestInterface $request, \Throwable $error): TestingFailureResponse
    {
        $response = FailureResponse::fromException($error, $request->getID());

        $this->parent->dispatch($response);

        return new TestingFailureResponse($response);
    }

    /**
     * {@inheritDoc}
     */
    public function request(RequestInterface $request, ?WorkflowContextInterface $context = null): PromiseInterface
    {
        if (!$request instanceof TestingRequest) {
            $request = new TestingRequest($request);
        }

        return parent::request($request, $context);
    }
}
