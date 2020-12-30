<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client;

use JetBrains\PhpStorm\ExpectedValues;
use Spiral\Attributes\AttributeReader;
use Spiral\Attributes\ReaderInterface;
use Temporal\Internal\Declaration\Reader\WorkflowReader;
use Temporal\Internal\Marshaller\Mapper\AttributeMapperFactory;
use Temporal\Internal\Marshaller\Marshaller;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Workflow\WorkflowProxy;
use Temporal\Internal\Workflow\WorkflowStub;
use Temporal\Worker\Transport\RpcConnectionInterface;

class ClientConnection implements ClientInterface
{
    /**
     * @var RpcConnectionInterface
     */
    private RpcConnectionInterface $rpc;

    /**
     * @var MarshallerInterface
     */
    private MarshallerInterface $marshaller;

    /**
     * @var ReaderInterface
     */
    private ReaderInterface $reader;

    /**
     * @var ClientOptions
     */
    private ClientOptions $options;

    /**
     * @param RpcConnectionInterface $rpc
     * @param ClientOptions $options
     */
    public function __construct(RpcConnectionInterface $rpc, ClientOptions $options)
    {
        $this->rpc = $rpc;
        $this->options = $options;
        $this->reader = new AttributeReader();

        $this->marshaller = new Marshaller(
            new AttributeMapperFactory($this->reader)
        );
    }

    /**
     * {@inheritDoc}
     */
    #[ExpectedValues(flagsFromClass: ReloadGroup::class)]
    public function reload(int $group = ReloadGroup::GROUP_ALL): iterable
    {
        $result = [];

        if (($group & ReloadGroup::GROUP_ACTIVITIES) === ReloadGroup::GROUP_ACTIVITIES) {
            $result[ReloadGroup::GROUP_ACTIVITIES] =
                $this->rpc->call('resetter.Reset', 'activities');
        }

        if (($group & ReloadGroup::GROUP_WORKFLOWS) === ReloadGroup::GROUP_WORKFLOWS) {
            $result[ReloadGroup::GROUP_WORKFLOWS] =
                $this->rpc->call('resetter.Reset', 'workflows');
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function newWorkflowStub(string $class, WorkflowOptions $options = null): WorkflowProxy
    {
        $workflows = (new WorkflowReader($this->reader))->fromClass($class);

        $options ??= new WorkflowOptions();

        return new WorkflowProxy($this->rpc, $this->marshaller, $class, $workflows, $options);
    }

    /**
     * {@inheritDoc}
     */
    public function newUntypedWorkflowStub(string $name, WorkflowOptions $options = null): WorkflowStubInterface
    {
        $options ??= new WorkflowOptions();

        return new WorkflowStub($this->rpc, $this->marshaller, $name, $options);
    }

    /**
     * {@inheritDoc}
     */
    public function newActivityClient(): ActivityCompletionClientInterface
    {
        return new ActivityCompletionClient($this->rpc, $this->options->namespace);
    }
}
