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
use Temporal\Api\Workflowservice\V1\GetSystemInfoRequest;
use Temporal\Api\Workflowservice\V1\WorkflowServiceClient;
use Temporal\Client\Common\BackoffThrottler;
use Temporal\Client\Common\RpcRetryOptions;
use Temporal\Client\Common\ServerCapabilities;
use Temporal\Client\GRPC\Connection\Connection;
use Temporal\Client\GRPC\Connection\ConnectionInterface;
use Temporal\Exception\Client\CanceledException;
use Temporal\Exception\Client\ServiceClientException;
use Temporal\Exception\Client\TimeoutException;
use Temporal\Interceptor\GrpcClientInterceptor;
use Temporal\Internal\Interceptor\Pipeline;

abstract class BaseClient implements ServiceClientInterface
{
    public const RETRYABLE_ERRORS = [
        StatusCode::RESOURCE_EXHAUSTED,
        StatusCode::UNAVAILABLE,
        StatusCode::UNKNOWN,
    ];

    /** @var null|Closure(string $method, object $arg, ContextInterface $ctx): object */
    private ?Closure $invokePipeline = null;

    private Connection $connection;
    private ContextInterface $context;
    private \Stringable|string $apiKey = '';

    /**
     * @param WorkflowServiceClient|Closure(): WorkflowServiceClient $workflowService Service Client or its factory
     *
     * @private Use static factory methods instead
     * @see self::create()
     * @see self::createSSL()
     */
    final public function __construct(WorkflowServiceClient|Closure $workflowService)
    {
        if ($workflowService instanceof WorkflowServiceClient) {
            \trigger_error(
                'Creating a ServiceClient instance via constructor is deprecated. Use static factory methods instead.',
                \E_USER_DEPRECATED,
            );
            $workflowService = static fn(): WorkflowServiceClient => $workflowService;
        }

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
     * Set the authentication token for the service client.
     *
     * This is the equivalent of providing an "Authorization" header with "Bearer " + the given key.
     * This will overwrite any "Authorization" header that may be on the context before each request to the
     * Temporal service.
     * You may pass your own {@see \Stringable} implementation to be able to change the key dynamically.
     */
    public function withAuthKey(\Stringable|string $key): static
    {
        $clone = clone $this;
        $clone->apiKey = $key;
        return $clone;
    }

    /**
     * Close the communication channel associated with this stub.
     */
    public function close(): void
    {
        $this->connection->disconnect();
    }

    /**
     * @param non-empty-string $address Temporal service address in format `host:port`
     * @return static
     * @psalm-suppress UndefinedClass
     */
    public static function create(string $address): static
    {
        if (!\extension_loaded('grpc')) {
            throw new \RuntimeException('The gRPC extension is required to use Temporal Client.');
        }

        return new static(static fn(): WorkflowServiceClient => new WorkflowServiceClient(
            $address,
            ['credentials' => \Grpc\ChannelCredentials::createInsecure()]
        ));
    }

    /**
     * @param non-empty-string $address Temporal service address in format `host:port`
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

        return new static(static fn(): WorkflowServiceClient => new WorkflowServiceClient($address, $options));
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
        $callable = $pipeline?->with($clone->call(...), 'interceptCall');
        $clone->invokePipeline = $callable === null ? null : $callable(...);
        return $clone;
    }

    public function getServerCapabilities(): ?ServerCapabilities
    {
        if ($this->connection->capabilities !== null) {
            return $this->connection->capabilities;
        }

        try {
            $systemInfo = $this->getSystemInfo(new GetSystemInfoRequest());
            $capabilities = $systemInfo->getCapabilities();

            if ($capabilities === null) {
                return null;
            }

            return $this->connection->capabilities = new ServerCapabilities(
                signalAndQueryHeader: $capabilities->getSignalAndQueryHeader(),
                internalErrorDifferentiation: $capabilities->getInternalErrorDifferentiation(),
                activityFailureIncludeHeartbeat: $capabilities->getActivityFailureIncludeHeartbeat(),
                supportsSchedules: $capabilities->getSupportsSchedules(),
                encodedFailureAttributes: $capabilities->getEncodedFailureAttributes(),
                buildIdBasedVersioning: $capabilities->getBuildIdBasedVersioning(),
                upsertMemo: $capabilities->getUpsertMemo(),
                eagerWorkflowStart: $capabilities->getEagerWorkflowStart(),
                sdkMetadata: $capabilities->getSdkMetadata(),
                countGroupByExecutionStatus: $capabilities->getCountGroupByExecutionStatus(),
            );
        } catch (ServiceClientException $e) {
            if ($e->getCode() === StatusCode::UNIMPLEMENTED) {
                return null;
            }

            throw $e;
        }
    }

    /**
     * @deprecated
     */
    public function setServerCapabilities(ServerCapabilities $capabilities): void
    {
        \trigger_error(
            'Method ' . __METHOD__ . ' is deprecated and will be removed in the next major release.',
            \E_USER_DEPRECATED,
        );
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
    protected function invoke(string $method, object $arg, ?ContextInterface $ctx = null): mixed
    {
        $ctx ??= $this->context;

        // Add the API key to the context
        $key = (string)$this->apiKey;
        if ($key !== '') {
            $ctx = $ctx->withMetadata([
                'Authorization' => ["Bearer $key"],
            ]);
        }

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
        $retryOption = RpcRetryOptions::fromRetryOptions($ctx->getRetryOptions());
        $initialIntervalMs = $congestionInitialIntervalMs = $throttler = null;

        do {
            ++$attempt;
            try {
                $options = $ctx->getOptions();
                $deadline = $ctx->getDeadline();
                if ($deadline !== null) {
                    $diff = (new \DateTime())->diff($deadline);
                    $options['timeout'] = CarbonInterval::instance($diff)->totalMicroseconds;
                }

                /** @var UnaryCall $call */
                $call = $this->connection->getWorkflowService()->{$method}($arg, $ctx->getMetadata(), $options);
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

                    if ($e->getCode() === StatusCode::CANCELLED) {
                        throw new CanceledException($e->getMessage(), $e->getCode(), $e);
                    }

                    // non retryable
                    throw $e;
                }

                if ($retryOption->maximumAttempts !== 0 && $attempt >= $retryOption->maximumAttempts) {
                    // Reached maximum attempts
                    throw $e;
                }

                if ($ctx->getDeadline() !== null && new DateTimeImmutable() > $ctx->getDeadline()) {
                    // Deadline is reached
                    throw new TimeoutException('Call timeout has been reached');
                }

                // Init interval values in milliseconds
                $initialIntervalMs ??= $retryOption->initialInterval === null
                    ? (int)CarbonInterval::millisecond(50)->totalMilliseconds
                    : (int)(new CarbonInterval($retryOption->initialInterval))->totalMilliseconds;
                $congestionInitialIntervalMs ??= $retryOption->congestionInitialInterval === null
                    ? (int)CarbonInterval::millisecond(1000)->totalMilliseconds
                    : (int)(new CarbonInterval($retryOption->congestionInitialInterval))->totalMilliseconds;

                $throttler ??= new BackoffThrottler(
                    maxInterval: $retryOption->maximumInterval !== null
                        ? (int)(new CarbonInterval($retryOption->maximumInterval))->totalMilliseconds
                        : $initialIntervalMs * 200,
                    maxJitterCoefficient: $retryOption->maximumJitterCoefficient,
                    backoffCoefficient: $retryOption->backoffCoefficient
                );

                // Initial interval always depends on the *most recent* failure.
                $baseInterval = $e->getCode() === StatusCode::RESOURCE_EXHAUSTED
                    ? $congestionInitialIntervalMs
                    : $initialIntervalMs;

                $wait = $throttler->calculateSleepTime(failureCount: $attempt, initialInterval: $baseInterval);

                // wait till the next call
                $this->usleep($wait);
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
