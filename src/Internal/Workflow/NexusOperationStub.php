<?php

declare(strict_types=1);

namespace Temporal\Internal\Workflow;

use React\Promise\PromiseInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\Type;
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
        $request = new ExecuteNexusOperation(
            endpoint: $this->options->endpoint,
            service: $this->options->service,
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
            EncodedValues::decodePromise($this->request($request), $returnType),
        );
    }

    protected function request(RequestInterface $request): PromiseInterface
    {
        return Workflow::getCurrentContext()->request($request);
    }
}
