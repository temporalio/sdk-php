<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
use Temporal\Nexus\Internal\Headers;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Workflow;
use Temporal\Workflow\NexusOperationCancellationType;
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
        return $this
            ->start($operation, $args, $returnType, $nexusHeaders)
            ->then(static fn(NexusOperationHandle $handle): PromiseInterface => $handle->getResult());
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
            nexusHeaders: Headers::normalize($nexusHeaders),
        );

        $startId = $startRequest->getID();

        $cancellable = $this->options->cancellationType !== NexusOperationCancellationType::Abandon;

        $operationToken = '';
        $resultPromise = $this->normalizeFailure(
            $this->request($startRequest, cancellable: $cancellable),
            $endpoint,
            $service,
            $operation,
            $operationToken,
        );
        $startedPromise = $this->normalizeFailure(
            $this->request(new GetNexusOperationStarted($startId)),
            $endpoint,
            $service,
            $operation,
            $operationToken,
        );

        return $startedPromise->then(
            static function (ValuesInterface $values) use (
                &$operationToken,
                $resultPromise,
                $returnType
            ): NexusOperationHandle {
                $envelope = $values->getValue(0, NexusStartEnvelope::class);
                if ($envelope->async) {
                    $operationToken = $envelope->token;
                }

                return new NexusOperationHandle(
                    operationToken: $envelope->async ? $envelope->token : null,
                    rawResult: $resultPromise,
                    returnType: $returnType,
                );
            },
        );
    }

    protected function request(RequestInterface $request, bool $cancellable = true): PromiseInterface
    {
        return Workflow::getCurrentContext()->request($request, $cancellable);
    }

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

    private function normalizeFailure(
        PromiseInterface $promise,
        string $endpoint,
        string $service,
        string $operation,
        string &$operationToken,
    ): PromiseInterface {
        return $promise->then(
            null,
            static function (\Throwable $e) use ($endpoint, $service, $operation, &$operationToken): never {
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
                    operationToken: $operationToken,
                    previous: $e,
                );
            },
        );
    }
}
