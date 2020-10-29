<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Workflow;

use Temporal\Client\Meta\ReaderInterface;
use Temporal\Client\Protocol\ClientInterface;
use Temporal\Client\Protocol\Command\RequestInterface;
use Temporal\Client\Protocol\Command\ResponseInterface;
use Temporal\Client\Protocol\DispatcherInterface;
use Temporal\Client\Protocol\Router;
use Temporal\Client\Protocol\RouterInterface;
use Temporal\Client\Worker\Declaration\Repository\WorkflowRepositoryInterface;
use Temporal\Client\Worker\Declaration\Repository\WorkflowRepositoryTrait;
use Temporal\Client\Worker\Uuid4;
use Temporal\Client\Worker\Worker;
use Temporal\Client\Workflow\Runtime\RunningWorkflows;

/**
 * @noinspection PhpSuperClassIncompatibleWithInterfaceInspection
 */
final class WorkflowWorker implements WorkflowRepositoryInterface, DispatcherInterface
{
    use WorkflowRepositoryTrait;

    /**
     * @var ReaderInterface
     */
    private ReaderInterface $reader;

    /**
     * @var RouterInterface
     */
    private RouterInterface $router;

    /**
     * @var string|null
     */
    private ?string $runId = null;

    /**
     * @param Worker $worker
     * @throws \Exception
     */
    public function __construct(Worker $worker)
    {
        $this->reader = $worker->getReader();

        $this->bootWorkflowRepositoryTrait();

        $running = new RunningWorkflows();

        $this->router = new Router();
        $this->router->add(new Router\StartWorkflow($this->workflows, $running, $worker));
        $this->router->add(new Router\InvokeQueryMethod($running));
        $this->router->add(new Router\InvokeSignalMethod($running));
        $this->router->add(new Router\DestroyWorkflow($running, $worker));
    }

    /**
     * @return string|null
     */
    public function getCurrentRunId(): ?string
    {
        return $this->runId;
    }

    /**
     * {@inheritDoc}
     */
    public function dispatch(RequestInterface $request, array $headers = []): ResponseInterface
    {
        if (isset($headers['rid'])) {
            $this->runId = $headers['rid'];
        }

        return $this->router->dispatch($request, $headers);
    }

    /**
     * @return ReaderInterface
     */
    protected function getReader(): ReaderInterface
    {
        return $this->reader;
    }
}
