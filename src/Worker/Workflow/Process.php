<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Worker\Workflow;

use Temporal\Client\Worker\Uuid4;
use Temporal\Client\Worker\WorkflowWorkerInterface;

/**
 * Worker state class at the time of initialization.
 *
 * @internal Process is an internal library class, please do not use it in your code.
 * @psalm-internal Temporal\Client\Worker
 */
final class Process
{
    /**
     * @var string
     */
    private string $id;

    /**
     * @var WorkflowWorkerInterface
     */
    private WorkflowWorkerInterface $worker;

    /**
     * @param WorkflowWorkerInterface $worker
     * @throws \Exception
     */
    public function __construct(WorkflowWorkerInterface $worker)
    {
        $this->worker = $worker;
        $this->id = Uuid4::create();
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return WorkflowWorkerInterface
     */
    public function getWorker(): WorkflowWorkerInterface
    {
        return $this->worker;
    }
}
