<?php

declare(strict_types=1);

namespace Temporal\Internal\Workflow;

use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\Type;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Exception\Failure\NexusOperationFailure;
use Temporal\Interceptor\HeaderInterface;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Transport\Request\CancelNexusOperationResult;
use Temporal\Internal\Transport\Request\ExecuteNexusOperation;
use Temporal\Internal\Transport\Request\GetNexusOperationResult;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Workflow;
use Temporal\Workflow\NexusOperationHandle;
use Temporal\Workflow\NexusOperationOptions;
use Temporal\Workflow\NexusOperationStubInterface;

final class NexusOperationStub implements NexusOperationStubInterface
{
    /** Default interval between GetNexusOperationResult retries for async ops. */
    private const DEFAULT_POLL_INTERVAL_SECONDS = 5;

    /**
     * @param MarshallerInterface<array> $marshaller
     */
    public function __construct(
        private readonly MarshallerInterface $marshaller,
        private readonly DataConverterInterface $dataConverter,
        private readonly NexusOperationOptions $options,
        private readonly HeaderInterface $header,
    ) {}

    public function getOptions(): NexusOperationOptions
    {
        return $this->options;
    }

    public function execute(
        string $operation,
        array $args = [],
        Type|string|\ReflectionClass|\ReflectionType|null $returnType = null,
        array $nexusHeaders = [],
    ): PromiseInterface {
        return $this->start($operation, $args, $returnType, $nexusHeaders)->then(
            static fn(NexusOperationHandle $handle): PromiseInterface => $handle->getResult(),
        );
    }

    public function start(
        string $operation,
        array $args = [],
        Type|string|\ReflectionClass|\ReflectionType|null $returnType = null,
        array $nexusHeaders = [],
    ): PromiseInterface {
        // Programming errors throw synchronously; runtime errors reject the promise.
        $endpoint = $this->options->endpoint;
        $service = $this->options->service;
        $this->assertOperationParams($endpoint, $service, $operation);

        $startRequest = new ExecuteNexusOperation(
            endpoint: $endpoint,
            service: $service,
            operation: $operation,
            args: EncodedValues::fromValues($args),
            options: $this->marshaller->marshal($this->options),
            header: $this->header,
            nexusHeaders: $nexusHeaders,
        );

        $startId = $startRequest->getID();

        return $this->request($startRequest)->then(
            fn(ValuesInterface $values): NexusOperationHandle => $this->buildHandle(
                $values,
                $startId,
                $returnType,
                $endpoint,
                $service,
                $operation,
            ),
        );
    }

    protected function request(RequestInterface $request, bool $waitResponse = true): PromiseInterface
    {
        return Workflow::getCurrentContext()->request($request, waitResponse: $waitResponse);
    }

    /**
     * Surface "missing endpoint/service/operation" locally — the server returns
     * an opaque "not found" otherwise.
     */
    private function assertOperationParams(string $endpoint, string $service, string $operation): void
    {
        if ($endpoint === '') {
            throw new \InvalidArgumentException(\sprintf(
                "Nexus stub for %s has no endpoint set. Call NexusOperationOptions::withEndpoint('your-endpoint') before passing options to newNexusServiceStub() or newUntypedNexusOperationStub().",
                $service !== '' ? "service '{$service}'" : 'this operation',
            ));
        }
        if ($service === '') {
            throw new \InvalidArgumentException(
                'Nexus service is empty; call NexusOperationOptions::withService() or pass a #[Service]-annotated interface to newNexusServiceStub()',
            );
        }
        if ($operation === '') {
            throw new \InvalidArgumentException('Nexus operation name must be a non-empty string');
        }
    }

    /**
     * Builds the {@see NexusOperationHandle} from the start-response envelope.
     *
     * Sync envelope (`async=false`) → `Payloads[1]` carries the result; we
     * slice it into a single-payload {@see ValuesInterface} so the handle's
     * `decodePromise` decodes it via `$returnType` uniformly with the async path.
     *
     * Async envelope (`async=true`) → kicks the polling coroutine; the handle's
     * result-promise resolves when GetNexusOperationResult finally returns it.
     */
    private function buildHandle(
        ValuesInterface $values,
        int $startId,
        Type|string|\ReflectionClass|\ReflectionType|null $returnType,
        string $endpoint,
        string $service,
        string $operation,
    ): NexusOperationHandle {
        $envelope = $values->getValue(0, NexusStartEnvelope::class);

        $resultDeferred = new Deferred();
        $rawResult = $this->normalizeFailure($resultDeferred->promise(), $endpoint, $service, $operation);

        if ($envelope->async) {
            $this->pollForResult($startId, $resultDeferred);
            return new NexusOperationHandle(
                operationToken: $envelope->token,
                rawResult: $rawResult,
                returnType: $returnType,
            );
        }

        $resultDeferred->resolve(EncodedValues::sliceValues(
            $this->dataConverter,
            $values,
            offset: 1,
            length: 1,
        ));
        return new NexusOperationHandle(
            operationToken: null,
            rawResult: $rawResult,
            returnType: $returnType,
        );
    }

    /**
     * Drives a normal `while` loop in a workflow coroutine, yielding on
     * GetNexusOperationResult and on a workflow timer between polls. Empty
     * response = not ready, retry. Non-empty = result, resolve. Cancel =
     * notify RR (fire-and-forget) and reject. Other failures propagate.
     */
    private function pollForResult(int $startId, Deferred $resultDeferred): void
    {
        $stub = $this;
        $interval = $this->options->pollInterval ?? self::DEFAULT_POLL_INTERVAL_SECONDS;
        Workflow::async(static function () use ($stub, $startId, $resultDeferred, $interval): \Generator {
            try {
                while (true) {
                    /** @var ValuesInterface $response */
                    $response = yield $stub->request(new GetNexusOperationResult($startId));

                    if ($response->count() > 0) {
                        $resultDeferred->resolve($response);
                        return;
                    }

                    yield Workflow::timer($interval);
                }
            } catch (CanceledFailure $e) {
                // Drop RR-side bookkeeping early so we don't wait for
                // Workflow::Close() to GC the entry. Fire-and-forget.
                $stub->request(new CancelNexusOperationResult($startId), waitResponse: false);
                $resultDeferred->reject($e);
                throw $e;
            } catch (\Throwable $e) {
                $resultDeferred->reject($e);
            }
        });
    }

    /**
     * Wrap bare CanceledFailure / local rejections in a NexusOperationFailure so
     * workflow code can match a single type. Server-side failures pass through
     * untouched; locally wrapped ones have empty scheduledEventId/operationToken.
     */
    private function normalizeFailure(
        PromiseInterface $promise,
        string $endpoint,
        string $service,
        string $operation,
    ): PromiseInterface {
        return $promise->then(null, static function (\Throwable $e) use ($endpoint, $service, $operation): never {
            if ($e instanceof NexusOperationFailure) {
                throw $e;
            }
            $message = $e instanceof CanceledFailure
                ? 'nexus operation cancelled'
                : 'nexus operation completed unsuccessfully';
            throw new NexusOperationFailure(
                message: $message,
                scheduledEventId: 0,
                endpoint: $endpoint,
                service: $service,
                operation: $operation,
                operationToken: '',
                previous: $e,
            );
        });
    }
}
