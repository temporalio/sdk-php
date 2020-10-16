<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace App\Server;

use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use React\Socket\ConnectionInterface;
use Temporal\Client\Protocol\Json;

abstract class Connection
{
    /**
     * @var LoopInterface
     */
    protected LoopInterface $loop;

    /**
     * @var ConnectionInterface
     */
    private ConnectionInterface $connection;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var GoRidge
     */
    private GoRidge $protocol;

    /**
     * @var string|int|null
     */
    private $runId;

    /**
     * @var int
     */
    private int $lastId = 1000;

    /**
     * @var array|Deferred[]
     */
    private array $promises = [];

    /**
     * @param LoopInterface $loop
     * @param ConnectionInterface $connection
     * @param LoggerInterface $logger
     */
    public function __construct(LoopInterface $loop, ConnectionInterface $connection, LoggerInterface $logger)
    {
        $this->connection = $connection;
        $this->logger = $logger;
        $this->loop = $loop;

        $this->protocol = new GoRidge($connection, function (string $message): void {
            $this->logger->debug($this->format('<<< Received Message ' . $this->json($message)));

            $this->onMessage($message);
        });
    }

    /**
     * @param string $message
     * @return string
     */
    private function format(string $message): string
    {
        return \sprintf('[%s] %s', $this->connection->getRemoteAddress(), $message);
    }

    /**
     * @param string $data
     * @return string
     * @throws \JsonException
     */
    private function json(string $data): string
    {
        return Json::encode(Json::decode($data), \JSON_PRETTY_PRINT);
    }

    /**
     * @param string $message
     * @throws \JsonException
     */
    private function onMessage(string $message): void
    {
        $data = Json::decode($message, \JSON_OBJECT_AS_ARRAY);

        $this->runId = $data['rid'];

        foreach ($data['commands'] as $command) {
            $id = $command['id'];

            // Is Request
            if (isset($command['command'])) {
                $deferred = new Deferred();
                $promise = $deferred->promise();

                try {
                    $this->onCommand($command['command'], $deferred);

                    $onFulfilled = function ($result) use ($id) {
                        $this->send(['result' => $result], $id);
                    };

                    $onRejected = function (\Throwable $e) use ($id) {
                        $this->send([
                            'error' => [
                                'code'    => $e->getCode(),
                                'message' => $e->getMessage(),
                            ],
                        ], $id);
                    };

                    $promise->then($onFulfilled, $onRejected);
                } catch (\Throwable $e) {
                    $deferred->reject($e);
                }

                continue;
            }

            // Is Response
            if (isset($command['result'])) {
                $this->promises[$id]->resolve($command['result']);

                continue;
            }

            // Is Error
            if (isset($command['error'])) {
                $this->promises[$id]->reject(
                    new \LogicException($command['error']['message'])
                );

                continue;
            }
        }
    }

    /**
     * @param string $name
     * @param Deferred $deferred
     */
    abstract protected function onCommand(string $name, Deferred $deferred): void;

    /**
     * @param array $payload
     * @param int|null $id
     * @throws \JsonException
     */
    private function send(array $payload, int $id = null): void
    {
        $payload = \array_merge($payload, ['id' => $id ?? $this->lastId++]);

        $data = Json::encode([
            'rid'      => $this->runId,
            'now'      => (new \DateTime('now'))->format(\DateTime::RFC3339),
            'commands' => [$payload],
        ]);

        $this->write($data);
    }

    /**
     * @param string $data
     * @throws \JsonException
     */
    private function write(string $data): void
    {
        $this->logger->debug($this->format('Proceed Message >>> ' . $this->json($data)));

        $this->protocol->write($data);
    }

    /**
     * @param string $command
     * @param array $params
     * @return PromiseInterface
     * @throws \JsonException
     */
    protected function request(string $command, array $params = []): PromiseInterface
    {
        $this->promises[$this->lastId] = $deferred = new Deferred();

        $this->send(['command' => $command, 'params' => $params]);

        return $deferred->promise();
    }

    /**
     * @param \Closure $callable
     */
    protected function process(\Closure $callable): void
    {
        /** @var \Generator $stream */
        $stream = $callable();

        $this->next($stream);
    }

    /**
     * @param \Generator $generator
     */
    private function next(\Generator $generator): void
    {
        if (! $generator->valid()) {
            return;
        }

        /** @var PromiseInterface $promise */
        $promise = $generator->current();

        $resolver = function ($result) use ($generator) {
            $generator->send($result);

            $this->next($generator);
        };

        $promise->then($resolver, fn(\Throwable $e) => $generator->throw($e));
    }
}
