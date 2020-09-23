<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Transport;

use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Spiral\Goridge\Message\ReceivedMessageInterface;
use Spiral\Goridge\ReceiverInterface;
use Spiral\Goridge\ResponderInterface;
use Temporal\Client\Transport\Request\InputRequest;
use Temporal\Client\Transport\Request\RequestInterface;
use Temporal\Client\Transport\Response\Response;
use Temporal\Client\Transport\Response\ResponseInterface;

class JsonRpcTransport implements RoutableTransportInterface
{
    /**
     * @var array|Deferred[]
     */
    private array $storage = [];

    /**
     * @var int
     */
    private int $sequence = 0;

    /**
     * @var ResponderInterface
     */
    private ResponderInterface $responder;

    /**
     * @var array|callable[]
     */
    private array $routes = [];

    /**
     * @param ReceiverInterface $receiver
     * @param ResponderInterface $responder
     * @throws \Exception
     */
    public function __construct(ReceiverInterface $receiver, ResponderInterface $responder)
    {
        $this->responder = $responder;

        $receiver->onReceive(function (ReceivedMessageInterface $message) {
            $this->handle($message);
        });
    }

    /**
     * @param ReceivedMessageInterface $message
     * @throws \JsonException
     */
    public function handle(ReceivedMessageInterface $message): void
    {
        $this->decodeResponse($this->decode($message), function (array $payload) {
            $this->resolveResponse($payload);
        });
    }

    /**
     * @param array $response
     * @param \Closure $each
     */
    private function decodeResponse(array $response, \Closure $each): void
    {
        if (isset($response['id'])) {
            $each($response);

            return;
        }

        foreach ($response as $batch) {
            if (! \is_array($batch) || ! isset($batch['id'])) {
                throw new \LogicException('JSON-RPC protocol error');
            }

            $this->decodeResponse($batch, $each);
        }
    }

    /**
     * @param array $data
     */
    private function resolveResponse(array $data): void
    {
        $id = $data['id'];

        switch (true) {
            case isset($data['result']):
                $this->handleResponse($id, $data);

                return;

            case isset($data['error']):
                $this->handleError($id, $data);

                return;

            case isset($data['method']) && \is_string($data['method']):
                $this->handleRequest($id, $data);

                return;

            default:
                // ERROR
        }
    }

    /**
     * @param ReceivedMessageInterface $message
     * @return array
     * @throws \JsonException
     */
    private function decode(ReceivedMessageInterface $message): array
    {
        return \json_decode($message->body, true, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * @param array $data
     * @return int
     */
    private function assertGetId(array $data): int
    {
        if (! isset($data['id'])) {
            throw new \LogicException('JSON-RPC protocol error');
        }

        return (int)$data['id'];
    }

    /**
     * @param int $id
     * @param array $data
     */
    private function handleResponse(int $id, array $data): void
    {
        if ($deferred = $this->storage[$id] ?? null) {
            try {
                $this->resolve($deferred, $data);
            } finally {
                unset($this->storage[$id]);
            }
        }
    }

    /**
     * @param Deferred $deferred
     * @param array $data
     */
    private function resolve(Deferred $deferred, array $data): void
    {
        if (! isset($data['result']) && ! \array_key_exists('result', $data)) {
            $deferred->reject(new \LogicException('JSON-RPC protocol error: Invalid "result" section received'));

            return;
        }

        $deferred->resolve(new Response($data['result']));
    }

    private function handleError(int $id, array $data)
    {
        // TODO
    }

    /**
     * @param int $id
     * @param array $data
     */
    private function handleRequest(int $id, array $data): void
    {
        $callback = $this->routes[$method = $data['method']] ?? null;

        if (! $callback) {
            throw new \DomainException('Unexpected received command "' . $method . '"');
        }

        // TODO add exception handling
        // TODO add response handling
        $result = $callback($data['params'] ?? null, $id);

        $this->sendResponse($id, $result);
    }

    /**
     * @param string $name
     * @param callable $then
     */
    public function route(string $name, callable $then): void
    {
        $this->routes[$name] = $then;
    }

    /**
     * @param RequestInterface $request
     * @return PromiseInterface
     * @throws \JsonException
     */
    public function send(RequestInterface $request): PromiseInterface
    {
        $this->responder->send($this->requestToJson($request), 0);

        return ($this->storage[$this->sequence] = new Deferred())
            ->promise();
    }

    /**
     * @param int $id
     * @param mixed $result
     * @throws \JsonException
     */
    private function sendResponse(int $id, $result): void
    {
        $this->responder->send($this->responseToJson($id, $result), 0);
    }

    /**
     * @param int $id
     * @param mixed $result
     * @return string
     * @throws \JsonException
     */
    private function responseToJson(int $id, $result): string
    {
        $payload = ['id' => $id, 'result' => $result];

        return $this->toJson($payload);
    }

    /**
     * @param RequestInterface $command
     * @return string
     * @throws \JsonException
     */
    private function requestToJson(RequestInterface $command): string
    {
        $payload = [
            'id'     => ++$this->sequence,
            'method' => $command->getName(),
            'params' => $command->getPayload(),
        ];

        return $this->toJson($payload);
    }

    /**
     * @param array $payload
     * @return string
     * @throws \JsonException
     */
    private function toJson(array $payload): string
    {
        return \json_encode($payload, \JSON_THROW_ON_ERROR);
    }
}
