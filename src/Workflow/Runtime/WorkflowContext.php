<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Workflow\Runtime;

use React\Promise\PromiseInterface;
use Temporal\Client\Protocol\Queue\QueueInterface;
use Temporal\Client\Worker\WorkerInterface;
use Temporal\Client\Workflow\Protocol\WorkflowProtocolInterface;

/**
 * @psalm-type WorkflowContextParams = array {
 *      name: string,
 *      wid: string,
 *      rid: string,
 *      taskQueue?: string,
 *      payload?: mixed,
 * }
 */
final class WorkflowContext implements WorkflowContextInterface
{
    use InteractWithQueueTrait;

    /**
     * @var string
     */
    private const KEY_NAME = 'name';

    /**
     * @var string
     */
    private const KEY_WORKFLOW_ID = 'wid';

    /**
     * @var string
     */
    private const KEY_WORKFLOW_RUN_ID = 'rid';

    /**
     * @var string
     */
    private const KEY_TASK_QUEUE = 'taskQueue';

    /**
     * @var string
     */
    private const KEY_PAYLOAD = 'payload';

    /**
     * @psalm-var WorkflowContextParams
     * @var array
     */
    private array $params;

    /**
     * @param WorkflowProtocolInterface $protocol
     * @param array $params
     */
    public function __construct(WorkflowProtocolInterface $protocol, array $params)
    {
        $this->params = $params;
        $this->protocol = $protocol;
    }

    /**
     * @return \DateTimeInterface
     */
    public function now(): \DateTimeInterface
    {
        return $this->protocol->getCurrentTickTime();
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->params[self::KEY_NAME] ?? 'unknown';
    }

    /**
     * {@inheritDoc}
     */
    public function getId(): string
    {
        return $this->params[self::KEY_WORKFLOW_ID] ?? 'unknown';
    }

    /**
     * {@inheritDoc}
     */
    public function getRunId(): string
    {
        return $this->params[self::KEY_WORKFLOW_RUN_ID] ?? 'unknown';
    }

    /**
     * {@inheritDoc}
     */
    public function getTaskQueue(): string
    {
        return $this->params[self::KEY_TASK_QUEUE] ?? WorkerInterface::DEFAULT_TASK_QUEUE;
    }

    /**
     * @return array
     */
    public function getPayload(): array
    {
        return (array)($this->params[self::KEY_PAYLOAD] ?? []);
    }
}
