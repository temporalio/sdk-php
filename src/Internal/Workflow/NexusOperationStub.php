<?php

declare(strict_types=1);

namespace Temporal\Internal\Workflow;

use React\Promise\PromiseInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\Type;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Exception\Failure\NexusOperationFailure;
use Temporal\Interceptor\HeaderInterface;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Transport\Request\ExecuteNexusOperation;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Workflow;
use Temporal\Workflow\NexusOperationHandle;
use Temporal\Workflow\NexusOperationOptions;
use Temporal\Workflow\NexusOperationStubInterface;

final class NexusOperationStub implements NexusOperationStubInterface
{
    /** @var MarshallerInterface<array> */
    private MarshallerInterface $marshaller;

    private NexusOperationOptions $options;
    private HeaderInterface $header;

    /**
     * @param MarshallerInterface<array> $marshaller
     */
    public function __construct(
        MarshallerInterface $marshaller,
        NexusOperationOptions $options,
        HeaderInterface $header,
    ) {
        $this->marshaller = $marshaller;
        $this->options = $options;
        $this->header = $header;
    }

    public function getOptions(): NexusOperationOptions
    {
        return $this->options;
    }

    public function execute(
        string $operation,
        array $args = [],
        Type|string|\ReflectionClass|\ReflectionType|null $returnType = null,
    ): PromiseInterface {
        return $this->start($operation, $args, $returnType)->getResult();
    }

    public function start(
        string $operation,
        array $args = [],
        Type|string|\ReflectionClass|\ReflectionType|null $returnType = null,
    ): NexusOperationHandle {
        // Wire boundary validation — server-side would reject empty
        // endpoint/service/operation with an opaque "not found", so surface
        // the real cause (forgotten `withEndpoint()` etc.) right here.
        $endpoint = $this->options->endpoint;
        $service = $this->options->service;
        if ($endpoint === '') {
            throw new \InvalidArgumentException(
                'Nexus endpoint is empty; call NexusOperationOptions::withEndpoint() before starting the operation',
            );
        }
        if ($service === '') {
            throw new \InvalidArgumentException(
                'Nexus service is empty; call NexusOperationOptions::withService() or pass a #[Service]-annotated interface to newNexusServiceStub()',
            );
        }
        /** @psalm-suppress TypeDoesNotContainType — defensive runtime guard for callers that silence psalm */
        if ($operation === '') {
            throw new \InvalidArgumentException('Nexus operation name must be a non-empty string');
        }

        $request = new ExecuteNexusOperation(
            endpoint: $endpoint,
            service: $service,
            operation: $operation,
            args: EncodedValues::fromValues($args),
            options: $this->marshaller->marshal($this->options),
            header: $this->header,
        );

        // Wrap in NexusOperationHandle so workflows can start the operation,
        // do other work, and await later. The current wire starts+waits
        // atomically, so the promise only resolves on completion; future
        // wire extensions can surface the operation token earlier without
        // breaking this API.
        return new NexusOperationHandle(
            EncodedValues::decodePromise($this->normalizeFailure($this->request($request), $endpoint, $service, $operation), $returnType),
        );
    }

    /**
     * Normalize the failure envelope so workflow code can rely on a single
     * exception type — matches the Java SDK contract where every rejection
     * of a Nexus operation future surfaces as `NexusOperationFailure` with
     * the original cause attached as `$previous`.
     *
     * Server-originated failures (handler error, schedule_to_close timeout,
     * cancel of a started op) already arrive wrapped via the
     * NexusOperationFailureInfo proto and pass through unchanged. Locally
     * resolved cases — Go SDK's `WaitRequested`/`TryCancel` early ack which
     * surfaces a bare `CanceledFailure`, or PHP-side scope cancel before the
     * request leaves RR — get wrapped here.
     *
     * `scheduledEventId` and `operationToken` are zero/empty for locally
     * wrapped failures because they're server-side concepts not available
     * on the caller path; consumers reading those fields should branch on
     * the previous-exception type.
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

    protected function request(RequestInterface $request): PromiseInterface
    {
        return Workflow::getCurrentContext()->request($request);
    }
}
