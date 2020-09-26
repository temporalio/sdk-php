<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Protocol;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Temporal\Client\Exception\ProtocolException;
use Temporal\Client\Protocol\Message\ErrorResponse;
use Temporal\Client\Protocol\Message\ErrorResponseInterface;
use Temporal\Client\Protocol\Message\MessageInterface;
use Temporal\Client\Protocol\Message\Request;
use Temporal\Client\Protocol\Message\RequestInterface;
use Temporal\Client\Protocol\Message\ResponseInterface;
use Temporal\Client\Protocol\Message\SuccessResponse;
use Temporal\Client\Protocol\Message\SuccessResponseInterface;
use Temporal\Client\Protocol\Transport\TransportInterface;

/**
 * @psalm-type ErrorResponseMessage = array {
 *      id: int|null,
 *      error: array {
 *          code: int,
 *          message: string,
 *          data?: mixed
 *      }
 * }
 * @psalm-type SuccessResponseMessage = array {
 *      id: int,
 *      result: mixed
 * }
 * @psalm-type RequestMessage = array {
 *      id: int,
 *      method: string,
 *      params?: array
 * }
 * @psalm-type SingleMessage = ErrorResponseMessage | RequestMessage | SuccessResponseMessage
 * @psalm-type BatchMessage = non-empty-array<array-key, SingleMessage>
 * @psalm-type RequestSubscription = \Closure(RequestInterface, Deferred): void
 */
final class JsonRpcProtocol implements DuplexProtocolInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;
    use LoggerTrait;

    /**
     * @var string
     */
    private const ERROR_UNIQUE_ID =
        'Can not to send a message, because a request with the same ' .
        'identifier #%s has already been sent earlier';

    /**
     * @var string
     */
    private const ERROR_MISSING_REQUEST =
        'The client did not send a request with identifier #%s or the ' .
        'response with this identifier has already been processed earlier';

    /**
     * @var TransportInterface
     */
    private TransportInterface $transport;

    /**
     * @var array|Deferred[]
     */
    private array $requests = [];

    /**
     * @var \Closure
     */
    private \Closure $onRequest;

    /**
     * @param TransportInterface $transport
     * @param \Closure $onRequest
     * @param LoggerInterface|null $logger
     */
    public function __construct(TransportInterface $transport, \Closure $onRequest, LoggerInterface &$logger = null)
    {
        $this->transport = $transport;
        $this->onRequest = $onRequest;
        $this->logger = &$logger;

        $this->listenIncomingMessages($transport);
    }

    /**
     * The method starts a listener for all incoming messages and redirects
     * to the desired emitter:
     *
     *  - {@see JsonRpcProtocol::emitRequest()} in the case that the incoming
     *      message is a rpc request.
     *  - {@see JsonRpcProtocol::emitResponse()} in the case that the incoming
     *      message is a rpc success or error response.
     *
     * @param TransportInterface $transport
     * @return void
     */
    private function listenIncomingMessages(TransportInterface $transport): void
    {
        $transport->onRequest(function (string $data) {
            $result = $this->parse($data);

            foreach (\is_iterable($result) ? $result : [$result] as $request) {
                if ($request instanceof RequestInterface) {
                    $this->emitRequest($request);
                } else {
                    $this->emitResponse($request);
                }
            }
        });
    }

    /**
     * @psalm-return iterable<array-key, MessageInterface>|MessageInterface
     *
     * @param string $json
     * @return MessageInterface|MessageInterface[]
     */
    private function parse(string $json)
    {
        try {
            $data = Json::decode($json, \JSON_OBJECT_AS_ARRAY);
        } catch (\JsonException $e) {
            throw new ProtocolException($e->getMessage(), ErrorResponse::CODE_PARSE_ERROR, $e);
        }

        return $this->decode($data);
    }

    /**
     * @psalm-param SingleMessage|BatchMessage $data
     * @psalm-return iterable<array-key, MessageInterface>|MessageInterface
     *
     * @param array $data
     * @return MessageInterface|MessageInterface[]
     */
    private function decode(array $data)
    {
        return isset($data['id'])
            ? $this->decodeSingleMessage($data)
            : $this->decodeBatchMessage($data);
    }

    /**
     * @psalm-param SingleMessage $data
     *
     * @param array $data
     * @return MessageInterface
     */
    private function decodeSingleMessage(array $data): MessageInterface
    {
        switch (true) {
            case isset($data['error']) && \is_array($data['error']):
                return $this->decodeErrorResponseMessage($data);

            case isset($data['result']):
                return $this->decodeSuccessResponseMessage($data);

            case isset($data['method']):
                return $this->decodeRequestMessage($data);

            default:
                $error = 'Unrecognized JSON-RPC message format';
                throw new ProtocolException($error, ErrorResponse::CODE_INVALID_REQUEST);
        }
    }

    /**
     * Converts an associative array to an error response message. Each such
     * array should contain the following fields:
     *
     * - "error":   REQUIRED object field, which must contain the following
     *              values:
     *
     *      - "code":       REQUIRED integer value that indicates the error type
     *                      that occurred.
     *
     *      - "message":    REQUIRED string value providing a short description
     *                      of the error. The message SHOULD be limited to a
     *                      concise single sentence.
     *
     *      - "data":       OPTIONAL scalar, array or object value that contains
     *                      additional information about the error. The value of
     *                      this member is defined by the Server (e.g. detailed
     *                      error information, nested errors etc.).
     *
     * - "id":      REQUIRED integer, string, or nullable value. If there was
     *              an error in detecting the id in the Request object (e.g.
     *              Parse error/Invalid Request), it MUST be Null.
     *
     * @psalm-param ErrorResponseMessage
     *
     * @param array $data
     * @return ErrorResponseInterface
     */
    private function decodeErrorResponseMessage(array $data): ErrorResponseInterface
    {
        [$id, $code, $message, $additional] = [
            $data['id'] ?? null,
            $data['error']['code'] ?? null,
            $data['error']['message'] ?? null,
            $data['error']['data'] ?? null,
        ];

        $isValid = ($id === null || \is_int($id)) && \is_int($code) && \is_string($message);

        if (!$isValid) {
            $error = 'Invalid JSON-RPC error message format';

            throw new ProtocolException($error, ErrorResponse::CODE_INVALID_REQUEST);
        }

        return new ErrorResponse($message, $code, $additional, $id);
    }

    /**
     * Converts an associative array to a success response message. Each such
     * array should contain the following fields:
     *
     * - "id":      REQUIRED integer or string value.
     *
     * - "result":  REQUIRED scalar, array or object value.
     *
     * @see https://www.jsonrpc.org/specification#response_object
     * @psalm-param SuccessResponseMessage $data
     *
     * @param array $data
     * @return SuccessResponseInterface
     */
    private function decodeSuccessResponseMessage(array $data): SuccessResponseInterface
    {
        [$id, $result] = [
            $data['id'] ?? null,
            $data['result'] ?? null,
        ];

        if (!\is_int($id)) {
            $error = 'Invalid JSON-RPC response message format';

            throw new ProtocolException($error, ErrorResponse::CODE_PARSE_ERROR);
        }

        return new SuccessResponse($result, $id);
    }

    /**
     * Converts an associative array to a request message. Each such array
     * should contain the following fields:
     *
     * - "id":      REQUIRED integer or string value.
     *
     * - "method":  REQUIRED string value containing the name of the method to
     *              be invoked.
     *              Method names that begin with the word rpc followed by a
     *              period character (U+002E or ASCII 46) are reserved for
     *              rpc-internal methods and extensions and MUST NOT be used
     *              for anything else.
     *
     * - "params":  OPTIONAL array or object value that holds the parameter
     *              values to be used during the invocation of the method.
     *
     * @see https://www.jsonrpc.org/specification#request_object
     * @psalm-param RequestMessage $data
     *
     * @param array $data
     * @return RequestInterface
     */
    private function decodeRequestMessage(array $data): RequestInterface
    {
        [$id, $method, $params] = [
            $data['id'] ?? null,
            $data['method'] ?? null,
            $data['params'] ?? null,
        ];

        //
        // - "id" must be an int
        // - "method" must be a string
        // - "params" must be an array
        //
        $isValid = \is_int($id) && \is_string($method) && ($params === null || \is_array($params));

        if (!$isValid) {
            $error = 'Invalid JSON-RPC request message format';

            throw new ProtocolException($error, ErrorResponse::CODE_INVALID_REQUEST);
        }

        return new Request($method, $params ?? [], $id);
    }

    /**
     * @psalm-param BatchMessage $messages
     * @psalm-return non-empty-array<array-key, MessageInterface>
     *
     * @param array $messages
     * @return array
     */
    private function decodeBatchMessage(array $messages): array
    {
        $result = [];

        foreach ($messages as $message) {
            $result[] = $this->decodeSingleMessage($message);
        }

        if (!\count($result)) {
            throw new ProtocolException('An empty batch JSON-RPC request', ErrorResponse::CODE_INVALID_REQUEST);
        }

        return $result;
    }

    /**
     * The method transfers control to the subscriber by calling it
     * with two arguments:
     *
     *  - {@see RequestInterface} as first argument.
     *  - {@see Deferred} as second argument:
     *      1) Resolving ({@see Deferred::resolve()}) this instance will send a
     *      correct successful response ({@see SuccessResponse}) to the server.
     *      2) Rejecting ({@see Deferred::reject()}) of this instance will send
     *      an error response ({@see ErrorResponse}) to the server.
     *
     * @param RequestInterface $request
     */
    private function emitRequest(RequestInterface $request): void
    {
        $this->debug('  <<< RPC Request', $request->toArray());

        $deferred = new Deferred();

        $id = $request->getId();
        $completed = false;

        $deferred->promise()
            ->always(fn() => $completed = true)
            ->then($this->onFulfilled($id), $this->onRejected($id))
        ;

        try {
            ($this->onRequest)($request, $deferred);
        } catch (\Throwable $e) {
            // What should we do in the case that the Promise has already been
            // resolved and then an error has occurred?
            if (!$completed) {
                $deferred->reject($e);
            }
        }
    }

    /**
     * @param string|int $id
     * @return \Closure
     */
    private function onFulfilled($id): \Closure
    {
        return function ($data) use ($id): void {
            $this->reply(new SuccessResponse($data, $id));
        };
    }

    /**
     * @param ResponseInterface $response
     * @throws \JsonException
     */
    public function reply(ResponseInterface $response): void
    {
        $this->debug('>>>   RPC Response', $response->toArray());

        $this->transport->send($this->toJson($response->toArray()));
    }

    /**
     * @param string|int $id
     * @return \Closure
     */
    private function onRejected($id): \Closure
    {
        return function (\Throwable $error) use ($id) {
            $wrapped = $this->onError($error);

            $this->reply(new ErrorResponse($wrapped->getMessage(), $wrapped->getCode(), null, $id));
        };
    }

    /**
     * @param \Throwable $e
     * @return \Throwable
     */
    private function onError(\Throwable $e): \Throwable
    {
        $code = $e->getCode();

        if ($code > -32099 && $code < -32000) {
            return $e;
        }

        switch (true) {
            case $e instanceof \InvalidArgumentException:
                return $this->wrapErrorWithCode($e, ErrorResponse::CODE_INVALID_PARAMETERS);

            case $e instanceof \BadFunctionCallException:
                return $this->wrapErrorWithCode($e, ErrorResponse::CODE_METHOD_NOT_FOUND);

            default:
                return $this->wrapErrorWithCode($e, ErrorResponse::CODE_INTERNAL_ERROR);
        }
    }

    /**
     * @param \Throwable $source
     * @param int $code
     * @return \Throwable
     */
    private function wrapErrorWithCode(\Throwable $source, int $code): \Throwable
    {
        $class = \get_class($source);

        return new $class($source->getMessage(), $code, $source->getPrevious());
    }

    /**
     * @param ResponseInterface $response
     * @throws \JsonException
     */
    private function emitResponse(ResponseInterface $response): void
    {
        $this->debug('  <<< RPC Response', $response->toArray());

        if (!$this->isRegisteredDeferred($response)) {
            $error = \sprintf(self::ERROR_MISSING_REQUEST, $response->getId());

            $this->reply(new ErrorResponse($error, ErrorResponse::CODE_INVALID_REQUEST));

            return;
        }

        $deferred = $this->requests[$response->getId()];

        unset($this->requests[$response->getId()]);

        switch (true) {
            case $response instanceof SuccessResponse:
                // But what to do when an error occurs during the resolving?
                $deferred->resolve($response->getResult());

                return;

            case $response instanceof ErrorResponse:
                $deferred->reject($response->toException());

                return;

            default:
                // This kind of situation doesn't seem to happen =)
                $error = \sprintf('Unrecognized response type %s', \get_class($response));
                throw new ProtocolException($error, ErrorResponse::CODE_INTERNAL_ERROR);
        }
    }

    /**
     * @param MessageInterface $message
     * @return bool
     */
    private function isRegisteredDeferred(MessageInterface $message): bool
    {
        return isset($this->requests[$message->getId()]);
    }

    /**
     * @param RequestInterface $request
     * @return PromiseInterface
     * @throws \JsonException
     */
    public function request(RequestInterface $request): PromiseInterface
    {
        $this->debug('>>>   RPC Request', $request->toArray());

        $data = $this->prepareForRequest($request);

        $this->transport->send($this->toJson($data));

        return $this->requests[$request->getId()]->promise();
    }

    /**
     * @param RequestInterface $request
     * @return array
     */
    private function prepareForRequest(RequestInterface $request): array
    {
        if ($this->isRegisteredDeferred($request)) {
            throw new ProtocolException(\sprintf(self::ERROR_UNIQUE_ID, $request->getId()));
        }

        $this->requests[$request->getId()] = new Deferred();

        return $request->toArray();
    }

    /**
     * {@inheritDoc}
     */
    public function batch(RequestInterface $request, RequestInterface ...$requests): iterable
    {
        $this->debug('>>>   RPC Request [Batch]', $requests);

        $formatted = [];

        foreach ([$request, ...$requests] as $current) {
            $formatted[] = $this->prepareForRequest($current);

            yield $this->requests[$current->getId()]->promise();
        }

        $this->transport->send($this->toJson($formatted));
    }

    /**
     * @param array $data
     * @param int $options
     * @return string
     * @throws \JsonException
     */
    private function toJson(array $data, int $options = 0): string
    {
        return Json::encode($data, $options);
    }

    /**
     * {@inheritDoc}
     */
    public function log($level, $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->log($level, $message, $context);
        }
    }

    /**
     * @param \Closure $then
     */
    public function onRequest(\Closure $then): void
    {
        $this->onRequest = $then;
    }
}
