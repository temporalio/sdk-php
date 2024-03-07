<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\GRPC;

use Carbon\CarbonInterval;
use Closure;
use Exception;
use Grpc\UnaryCall;
use Temporal\Api\Workflowservice\V1\WorkflowServiceClient;
use Temporal\Client\ServerCapabilities;
use Temporal\Exception\Client\ServiceClientException;
use Temporal\Exception\Client\TimeoutException;
use Temporal\Interceptor\GrpcClientInterceptor;
use Temporal\Internal\Interceptor\Pipeline;

abstract class BaseClient implements ServiceClientInterface
{
    const RETRYABLE_ERRORS = [
        StatusCode::RESOURCE_EXHAUSTED,
        StatusCode::UNAVAILABLE,
        StatusCode::UNKNOWN,
    ];
    private WorkflowServiceClient $workflowService;
    private ?ServerCapabilities $capabilities = null;

    /** @var null|Closure(string $method, object $arg, ContextInterface $ctx): object */
    private ?Closure $invokePipeline = null;

    /** @var callable */
    private $workflowServiceCloser;

    /**
     * @param WorkflowServiceClient $workflowService
     */
    public function __construct(WorkflowServiceClient $workflowService)
    {
        $this->workflowService = $workflowService;
        $this->workflowServiceCloser = $this->makeClientCloser($workflowService);
    }

    /**
     * Close the communication channel associated with this stub.
     */
    public function close(): void
    {
        ($this->workflowServiceCloser)();
    }

    /**
     * @param string $address
     * @return static
     * @psalm-suppress UndefinedClass
     */
    public static function create(string $address): static
    {
        if (!\extension_loaded('grpc')) {
            throw new \RuntimeException('The gRPC extension is required to use Temporal Client');
        }

        $client = new WorkflowServiceClient(
            $address,
            ['credentials' => \Grpc\ChannelCredentials::createInsecure()]
        );

        return new static($client);
    }

    /**
     * @param string $address
     * @param string $crt Certificate or cert file in x509 format.
     * @param string|null $clientKey
     * @param string|null $clientPem
     * @param string|null $overrideServerName
     * @return static
     *
     * @psalm-suppress UndefinedClass
     * @psalm-suppress UnusedVariable
     */
    public static function createSSL(
        string $address,
        string $crt,
        string $clientKey = null,
        string $clientPem = null,
        string $overrideServerName = null
    ): static {
        $options = [
            'credentials' => \Grpc\ChannelCredentials::createSsl(
                \is_file($crt) ? \file_get_contents($crt) : null,
                \is_file((string)$clientKey) ? \file_get_contents((string)$clientKey) : null,
                \is_file((string)$clientPem) ? \file_get_contents((string)$clientPem) : null
            )
        ];

        if ($overrideServerName !== null) {
            $options['grpc.default_authority'] = $overrideServerName;
            $options['grpc.ssl_target_name_override'] = $overrideServerName;
        }

        $client = new WorkflowServiceClient($address, $options);

        return new static($client);
    }

    /**
     * @param null|Pipeline<GrpcClientInterceptor, object> $pipeline
     *
     * @return static
     */
    final public function withInterceptorPipeline(?Pipeline $pipeline): static
    {
        $clone = clone $this;
        /** @see GrpcClientInterceptor::interceptCall() */
        $callable = $pipeline?->with(Closure::fromCallable([$clone, 'call']), 'interceptCall');
        $clone->invokePipeline = $callable === null ? null : Closure::fromCallable($callable);
        return $clone;
    }

    public function getServerCapabilities(): ?ServerCapabilities
    {
        return $this->capabilities;
    }

    public function setServerCapabilities(ServerCapabilities $capabilities): void
    {
        $this->capabilities = $capabilities;
    }

    /**
     * @param non-empty-string $method RPC method name
     * @param object $arg
     * @param ContextInterface|null $ctx
     *
     * @return mixed
     *
     * @throw ClientException
     */
    protected function invoke(string $method, object $arg, ContextInterface $ctx = null)
    {
        $ctx = $ctx ?? Context::default();

        return $this->invokePipeline !== null
            ? ($this->invokePipeline)($method, $arg, $ctx)
            : $this->call($method, $arg, $ctx);
    }

    /**
     * Call a gRPC method.
     * Used in {@see withInterceptorPipeline()}
     *
     * @param non-empty-string $method
     * @param object $arg
     * @param ContextInterface $ctx
     *
     * @return object
     *
     * @throws Exception
     */
    private function call(string $method, object $arg, ContextInterface $ctx): object
    {
        $attempt = 0;
        $retryOption = $ctx->getRetryOptions();

        $maxInterval = null;
        if ($retryOption->maximumInterval !== null) {
            $maxInterval = CarbonInterval::create($retryOption->maximumInterval);
        }

        $waitRetry = $retryOption->initialInterval ?? CarbonInterval::millisecond(500);
        $waitRetry = CarbonInterval::create($waitRetry);

        do {
            $attempt++;
            try {
                $options = $ctx->getOptions();
                $deadline = $ctx->getDeadline();
                if ($deadline !== null) {
                    $diff = (new \DateTime())->diff($deadline);
                    $options['timeout'] = CarbonInterval::instance($diff)->totalMicroseconds;
                }

                /** @var UnaryCall $call */
                $call = $this->workflowService->{$method}($arg, $ctx->getMetadata(), $options);
                [$result, $status] = $call->wait();

                if ($status->code !== 0) {
                    throw new ServiceClientException($status);
                }

                return $result;
            } catch (ServiceClientException $e) {
                if (!\in_array($e->getCode(), self::RETRYABLE_ERRORS, true)) {
                    if ($e->getCode() === StatusCode::DEADLINE_EXCEEDED) {
                        throw new TimeoutException($e->getMessage(), $e->getCode(), $e);
                    }

                    // non retryable
                    throw $e;
                }

                if ($retryOption->maximumAttempts !== 0 && $attempt >= $retryOption->maximumAttempts) {
                    throw $e;
                }

                if ($ctx->getDeadline() !== null && $ctx->getDeadline()->getTimestamp() > time()) {
                    throw new TimeoutException('Call timeout has been reached');
                }

                // wait till next call
                \usleep((int)$waitRetry->totalMicroseconds);

                $waitRetry = CarbonInterval::millisecond(
                    $waitRetry->totalMilliseconds + $retryOption->backoffCoefficient
                );

                if ($maxInterval !== null && $maxInterval->totalMilliseconds < $waitRetry->totalMilliseconds) {
                    $waitRetry = $maxInterval;
                }
            }
        } while (true);
    }

    /**
     * Makes an object that will close workflow service client connection on parent class destruct.
     *
     * @param WorkflowServiceClient $workflowServiceClient
     *
     * @return callable
     */
    private function makeClientCloser(WorkflowServiceClient $workflowServiceClient): callable
    {
        return new class ($workflowServiceClient) {
            public function __construct(public WorkflowServiceClient $client) { }

            public function __invoke(): void
            {
                $this->client->close();
            }

            public function __destruct()
            {
                $this->client->close();
            }
        };
    }
}
