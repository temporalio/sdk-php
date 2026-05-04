<?php

declare(strict_types=1);

namespace Temporal\Internal\Workflow;

use React\Promise\PromiseInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\Type;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Exception\Failure\NexusOperationFailure;
use Temporal\Interceptor\HeaderInterface;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Transport\Request\ExecuteNexusOperation;
use Temporal\Internal\Transport\Request\GetNexusOperationStarted;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Workflow;
use Temporal\Workflow\NexusOperationHandle;
use Temporal\Workflow\NexusOperationOptions;
use Temporal\Workflow\NexusOperationStubInterface;

final class NexusOperationStub implements NexusOperationStubInterface
{
    /**
     * @param MarshallerInterface<array> $marshaller
     */
    public function __construct(
        private readonly MarshallerInterface $marshaller,
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

        // Two parallel requests, mirroring ChildWorkflowStub::start(): the start request waits for completion, GetNexusOperationStarted waits for the started ack via RR's nexusStarted registry.
        $resultPromise = $this->normalizeFailure(
            $this->request($startRequest),
            $endpoint,
            $service,
            $operation,
        );
        $startedPromise = $this->request(new GetNexusOperationStarted($startId));

        return $startedPromise->then(
            fn(ValuesInterface $values): NexusOperationHandle => $this->buildHandle(
                $values,
                $resultPromise,
                $returnType,
            ),
        );
    }

    protected function request(RequestInterface $request): PromiseInterface
    {
        return Workflow::getCurrentContext()->request($request);
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
     * Build the handle from the start envelope; rawResult is the completion promise (sync resolves shortly after start, async resolves on handler finish).
     */
    private function buildHandle(
        ValuesInterface $startValues,
        PromiseInterface $resultPromise,
        Type|string|\ReflectionClass|\ReflectionType|null $returnType,
    ): NexusOperationHandle {
        $envelope = $startValues->getValue(0, NexusStartEnvelope::class);
        return new NexusOperationHandle(
            operationToken: $envelope->async ? $envelope->token : null,
            rawResult: $resultPromise,
            returnType: $returnType,
        );
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
