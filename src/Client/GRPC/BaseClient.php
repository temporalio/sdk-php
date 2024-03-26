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
use DateTimeImmutable;
use Exception;
use Fiber;
use Grpc\UnaryCall;
use Temporal\Api\Workflowservice\V1\WorkflowServiceClient;
use Temporal\Client\GRPC\Connection\Connection;
use Temporal\Client\GRPC\Connection\ConnectionInterface;
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

    /** @var null|Closure(string $method, object $arg, ContextInterface $ctx): object */
    private ?Closure $invokePipeline = null;

    private Connection $connection;
    private ContextInterface $context;

    /**
     * @param WorkflowServiceClient $workflowService
     */
    public function __construct(WorkflowServiceClient $workflowService)
    {
        $this->connection = new Connection($workflowService);
        $this->context = Context::default();
    }

    public function getContext(): ContextInterface
    {
        return $this->context;
    }

    public function withContext(ContextInterface $context): static
    {
        $clone = clone $this;
        $clone->context = $context;
        return $clone;
    }

    /**
     * Close the communication channel associated with this stub.
     */
    public function close(): void
    {
        $this->connection->close();
    }

    /**
     * @param string $address
     * @return static
     * @psalm-suppress UndefinedClass
     */
    public static function create(string $address): static
    {
        if (!\extension_loaded('grpc')) {
            throw new \RuntimeException('The gRPC extension is required to use Temporal Client.');
        }

        $client = new WorkflowServiceClient(
            $address,
            ['credentials' => \Grpc\ChannelCredentials::createInsecure()]
        );

        return new static($client);
    }

    /**
     * @param string $address
     * @param non-empty-string|null $crt Root certificates string or file in PEM format.
     *        If null provided, default gRPC root certificates are used.
     * @param non-empty-string|null $clientKey Client private key string or file in PEM format.
     * @param non-empty-string|null $clientPem Client certificate chain string or file in PEM format.
     * @param non-empty-string|null $overrideServerName
     * @return static
     *
     * @psalm-suppress UndefinedClass
     * @psalm-suppress UnusedVariable
     */
    public static function createSSL(
        string $address,
        string $crt = null,
        string $clientKey = null,
        string $clientPem = null,
        string $overrideServerName = null
    ): static {
        if (!\extension_loaded('grpc')) {
            throw new \RuntimeException('The gRPC extension is required to use Temporal Client.');
        }

        $loadCert = static function (?string $cert): ?string {
            return match (true) {
                $cert === null, $cert === '' => null,
                \is_file($cert) => false === ($content = \file_get_contents($cert))
                    ? throw new \InvalidArgumentException("Failed to load certificate from file `$cert`.")
                    : $content,
                default => $cert,
            };
        };

        $options = [
            'credentials' => \Grpc\ChannelCredentials::createSsl(
                $loadCert($crt),
                $loadCert($clientKey),
                $loadCert($clientPem),
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
        return $this->connection->capabilities;
    }

    public function setServerCapabilities(ServerCapabilities $capabilities): void
    {
        $this->connection->capabilities = $capabilities;
    }

    /**
     * Note: Experimental
     */
    public function getConnection(): ConnectionInterface {
        return $this->connection;
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
    protected function invoke(string $method, object $arg, ?ContextInterface $ctx = null)
    {
        $ctx ??= $this->context;

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
                $call = $this->connection->workflowService->{$method}($arg, $ctx->getMetadata(), $options);
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

                if ($ctx->getDeadline() !== null && $ctx->getDeadline() > new DateTimeImmutable()) {
                    throw new TimeoutException('Call timeout has been reached');
                }

                // wait till the next call
                $this->usleep((int)$waitRetry->totalMicroseconds);

                $waitRetry = CarbonInterval::millisecond(
                    $waitRetry->totalMilliseconds * $retryOption->backoffCoefficient
                );

                if ($maxInterval !== null && $maxInterval->totalMilliseconds < $waitRetry->totalMilliseconds) {
                    $waitRetry = $maxInterval;
                }
            }
        } while (true);
    }

    /**
     * @param int<0, max> $param Delay in microseconds
     */
    private function usleep(int $param): void
    {
        if (Fiber::getCurrent() === null) {
            \usleep($param);
            return;
        }

        $deadline = \microtime(true) + (float)($param / 1_000_000);

        while (\microtime(true) < $deadline) {
            Fiber::suspend();
        }
    }
}
