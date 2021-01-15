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
use Temporal\Internal\Client\ActivityCompletionClient;
use Temporal\Internal\Declaration\Reader\WorkflowReader;
use Temporal\Internal\Marshaller\Mapper\AttributeMapperFactory;
use Temporal\Internal\Marshaller\Marshaller;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Client\WorkflowProxy;
use Temporal\Internal\Client\WorkflowStub;
use Temporal\Workflow\WorkflowInterface;

class WorkflowClient implements WorkflowClientInterface
{
    private const ERROR_NON_INTERFACED_WORKFLOW_STUB =
        'Could not create a workflow stub %s from a class that does not contain the #[%s] attribute'
    ;

    /**
     * @var ServiceClientInterface
     */
    private ServiceClientInterface $serviceClient;

    /**
     * @var ClientOptions
     */
    private ClientOptions $clientOptions;

    /**
     * @var DataConverterInterface
     */
    private DataConverterInterface $converter;

    /**
     * @var ReaderInterface
     */
    private ReaderInterface $reader;

    /**
     * @var MarshallerInterface
     */
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
        $this->serviceClient = $serviceClient;
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
        return $this->serviceClient;
    }

    /**
     * {@inheritDoc}
     */
    public function newWorkflowStub(string $class, WorkflowOptions $options = null): WorkflowProxy
    {
        $workflow = (new WorkflowReader($this->reader))->fromClass($class);

        if (! $workflow->isInterfaced()) {
            throw new \InvalidArgumentException(
                \sprintf(self::ERROR_NON_INTERFACED_WORKFLOW_STUB, $class, WorkflowInterface::class)
            );
        }

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
            $this->serviceClient,
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
        return new ActivityCompletionClient(
            $this->serviceClient,
            $this->clientOptions,
            $this->converter
        );
    }
}
