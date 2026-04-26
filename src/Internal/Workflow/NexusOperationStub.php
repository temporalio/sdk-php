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
        array $nexusHeaders = [],
    ): PromiseInterface {
        return $this->start($operation, $args, $returnType, $nexusHeaders)->getResult();
    }

    public function start(
        string $operation,
        array $args = [],
        Type|string|\ReflectionClass|\ReflectionType|null $returnType = null,
        array $nexusHeaders = [],
    ): NexusOperationHandle {
        // Surface "missing endpoint/service/operation" locally — server returns opaque "not found".
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
            nexusHeaders: $nexusHeaders,
        );

        // Handle splits start/await for source-compat with future wire (token-on-async).
        return new NexusOperationHandle(
            EncodedValues::decodePromise($this->normalizeFailure($this->request($request), $endpoint, $service, $operation), $returnType),
        );
    }

    protected function request(RequestInterface $request): PromiseInterface
    {
        return Workflow::getCurrentContext()->request($request);
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
