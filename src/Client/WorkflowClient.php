<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client;

use JetBrains\PhpStorm\Immutable;
use Spiral\Attributes\AttributeReader;
use Spiral\Attributes\ReaderInterface;
use Temporal\Client\GRPC\ServiceClientInterface;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Exception\InvalidArgumentException;
use Temporal\Internal\Client\ActivityCompletionClient;
use Temporal\Internal\Declaration\Reader\WorkflowReader;
use Temporal\Internal\Marshaller\Mapper\AttributeMapperFactory;
use Temporal\Internal\Marshaller\Marshaller;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Client\WorkflowProxy;
use Temporal\Internal\Client\WorkflowStub;
use Temporal\Workflow\WorkflowRunInterface;
use Temporal\Workflow\WorkflowInterface;

class WorkflowClient implements WorkflowClientInterface
{
    private ServiceClientInterface $client;
    private ClientOptions $clientOptions;
    private DataConverterInterface $converter;
    private ReaderInterface $reader;
    private MarshallerInterface $marshaller;

    /**
     * @param ServiceClientInterface $serviceClient
     * @param ClientOptions|null $options
     * @param DataConverterInterface|null $converter
     */
    public function __construct(
        ServiceClientInterface $serviceClient,
        ClientOptions $options = null,
        DataConverterInterface $converter = null
    ) {
        $this->client = $serviceClient;
        $this->clientOptions = $options ?? new ClientOptions();
        $this->converter = $converter ?? DataConverter::createDefault();

        $this->reader = new AttributeReader();
        $this->marshaller = new Marshaller(
            new AttributeMapperFactory($this->reader)
        );
    }

    /**
     * @return ServiceClientInterface
     */
    public function getServiceClient(): ServiceClientInterface
    {
        return $this->client;
    }

    /**
     * Starts workflow in async mode.
     *
     * @param object|WorkflowStubInterface $workflow
     * @param mixed ...$args
     * @return WorkflowRunInterface
     */
    public function start($workflow, ...$args): WorkflowRunInterface
    {
        if ($workflow instanceof WorkflowProxy) {
            return $workflow->startAsync($args);
        }

        if ($workflow instanceof WorkflowStubInterface) {
            $workflow->start($args);
            return $workflow;
        }

        throw new InvalidArgumentException(
            sprintf(
                "Only workflow stubs can be started, %s given",
                is_object($workflow) ? get_class($workflow) : gettype($workflow)
            )
        );
    }

    /**
     * {@inheritDoc}
     */
    public function newWorkflowStub(string $class, WorkflowOptions $options = null): WorkflowProxy
    {
        $workflow = (new WorkflowReader($this->reader))->fromClass($class);

        return new WorkflowProxy(
            $this->newUntypedWorkflowStub($workflow->getID(), $options),
            $workflow,
            $class
        );
    }

    /**
     * {@inheritDoc}
     */
    public function newUntypedWorkflowStub(string $name, WorkflowOptions $options = null): WorkflowStubInterface
    {
        $options ??= new WorkflowOptions();

        return new WorkflowStub(
            $this->client,
            $this->clientOptions,
            $this->converter,
            $name,
            $options
        );
    }

    /**
     * {@inheritDoc}
     */
    public function newActivityCompletionClient(): ActivityCompletionClientInterface
    {
        return new ActivityCompletionClient($this->client, $this->clientOptions, $this->converter);
    }
}
