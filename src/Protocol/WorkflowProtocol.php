<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Protocol;

use React\Promise\PromiseInterface;
use Temporal\Client\Protocol\Command\RequestInterface;
use Temporal\Client\Protocol\Queue\QueueInterface;
use Temporal\Client\Protocol\Queue\SplQueue;
use Temporal\Client\Protocol\WorkflowProtocol\Context;
use Temporal\Client\Protocol\WorkflowProtocol\Decoder;
use Temporal\Client\Protocol\WorkflowProtocol\Encoder;

/**
 * @internal WorkflowProtocol is an internal library class, please do not use it in your code.
 * @psalm-internal Temporal\Client\Protocol
 */
final class WorkflowProtocol implements WorkflowProtocolInterface
{
    /**
     * @var \DateTimeZone
     */
    private \DateTimeZone $zone;

    /**
     * @var QueueInterface
     */
    private QueueInterface $queue;

    /**
     * @var Context
     */
    private Context $context;

    /**
     * WorkflowProtocol constructor.
     */
    public function __construct()
    {
        $this->zone = new \DateTimeZone('UTC');
        $this->queue = new SplQueue();
        $this->context = new Context($this->zone);
    }

    /**
     * @param RequestInterface $request
     * @return PromiseInterface
     */
    public function request(RequestInterface $request): PromiseInterface
    {
        $this->queue->push($request);

        return $this->context->promiseForRequest($request);
    }

    /**
     * @param string $request
     * @return string
     * @throws \JsonException
     */
    public function next(string $request): string
    {
        $this->parse($request);

        return $this->extractQueue();
    }

    /**
     * @param string $request
     * @throws \JsonException
     */
    private function parse(string $request): void
    {
        $data = Decoder::decode($request, $this->zone);

        $this->context->runId = $data['rid'];
        $this->context->now = $data['tickTime'];
    }

    /**
     * @return string
     * @throws \JsonException
     */
    private function extractQueue(): string
    {
        $now = new \DateTime('now', $this->zone);

        return Encoder::encode($now, $this->queue, $this->context->runId);
    }
}
