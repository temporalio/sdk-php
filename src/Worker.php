<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client;

use Psr\Container\ContainerInterface;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use Spiral\Core\Container;
use Spiral\Core\ResolverInterface;
use Temporal\Client\Declaration\ActivityInterface;
use Temporal\Client\Declaration\WorkflowInterface;
use Spiral\Goridge\ReceiverInterface;
use Spiral\Goridge\RelayInterface;
use Spiral\Goridge\ResponderInterface;
use Temporal\Client\Transport\JsonRpcTransport;
use Temporal\Client\Transport\Request\CreateWorker;
use Temporal\Client\Transport\Request\StartWorker;
use Temporal\Client\Transport\RoutableTransportInterface;
use Temporal\Client\Transport\TransportInterface;

class Worker implements MutableWorkerInterface
{
    /**
     * @var string
     */
    public const DEFAULT_WORKER_ID = 'default';

    /**
     * @var array|WorkflowInterface[]
     */
    private array $workflows = [];

    /**
     * @var array[]
     */
    private array $workflowOptions = [];

    /**
     * @var array|ActivityInterface[]
     */
    private array $activities = [];

    /**
     * @var array[]
     */
    private array $activityOptions = [];

    /**
     * @var TransportInterface
     */
    private TransportInterface $transport;

    /**
     * @var LoopInterface
     */
    private LoopInterface $loop;

    /**
     * @param Container $app
     * @param RoutableTransportInterface $transport
     * @param LoopInterface|null $loop
     */
    public function __construct(Container $app, RoutableTransportInterface $transport, LoopInterface $loop = null)
    {
        $this->loop = $loop ?? Factory::create();
        $this->transport = new Router($app, $transport, $this);
    }

    /**
     * @return TransportInterface
     */
    public function getTransport(): TransportInterface
    {
        return $this->transport;
    }

    /**
     * @param string $name
     * @return void
     */
    public function run(string $name = self::DEFAULT_WORKER_ID): void
    {
        $this->handshake($name);

        $this->loop->run();
    }

    /**
     * @param string $name
     */
    private function handshake(string $name): void
    {
        // STEP 1
        // STEP 2
        $this->transport->send(new CreateWorker(
            $this->declarationsToHandshake($this->workflows, $this->workflowOptions),
            $this->declarationsToHandshake($this->activities, $this->activityOptions),
            $name
        ));

        // STEP 3
        $this->transport->send(new StartWorker());
    }

    /**
     * @param array $declarations
     * @param array $options
     * @return array
     */
    private function declarationsToHandshake(array $declarations, array $options): array
    {
        $result = [];

        foreach ($declarations as $name => $_) {
            $result[] = \array_merge(['name' => $name], $options[$name] ?? []);
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function addWorkflow(WorkflowInterface $workflow, bool $override = false): void
    {
        $this->workflows[$name = $workflow->getName()] = $workflow;

        if ($override) {
            $this->workflowOptions[$name] = ['overwrite' => true];
        }
    }

    /**
     * @param string $name
     * @return WorkflowInterface|null
     */
    public function findWorkflow(string $name): ?WorkflowInterface
    {
        return $this->workflows[$name] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function addActivity(ActivityInterface $activity, bool $override = false): void
    {
        $this->activities[$name = $activity->getName()] = $activity;

        if ($override) {
            $this->activityOptions[$name] = ['overwrite' => true];
        }
    }

    /**
     * @param string $name
     * @return ActivityInterface|null
     */
    public function findActivity(string $name): ?ActivityInterface
    {
        return $this->activities[$name] ?? null;
    }
}
