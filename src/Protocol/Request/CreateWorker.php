<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Protocol\Request;

use Temporal\Client\Declaration\ActivityInterface;
use Temporal\Client\Declaration\WorkflowInterface;
use Temporal\Client\Protocol\Message\Request;

class CreateWorker extends Request
{
    /**
     * @var string
     */
    public const METHOD_NAME = 'CreateWorker';

    /**
     * @var string
     */
    public const PARAM_ACTIVITIES = 'activities';

    /**
     * @var string
     */
    public const PARAM_WORKFLOWS = 'workflows';

    /**
     * @param string $name
     */
    public function __construct(string $name)
    {
        parent::__construct(self::METHOD_NAME, [
            'taskQueue'            => $name,
            self::PARAM_WORKFLOWS  => [],
            self::PARAM_ACTIVITIES => [],
        ]);
    }

    /**
     * @param WorkflowInterface $workflow
     * @param array $options
     */
    public function addWorkflow(WorkflowInterface $workflow, array $options = []): void
    {
        $params = \array_merge($options, [
            'name'    => $workflow->getName(),
            'queries' => $this->iterableKeys($workflow->getQueryHandlers()),
            'signals' => $this->iterableKeys($workflow->getSignalHandlers()),
        ]);

        $this->extend(self::PARAM_WORKFLOWS, $params);
    }

    /**
     * @param iterable $iterable
     * @return array
     */
    private function iterableKeys(iterable $iterable): array
    {
        $result = [];

        foreach ($iterable as $key => $_) {
            $result[] = $key;
        }

        return $result;
    }

    /**
     * @param string $param
     * @param array $data
     */
    private function extend(string $param, array $data): void
    {
        $this->params[$param][] = $data;
    }

    /**
     * @param ActivityInterface $activity
     * @param array $options
     */
    public function addActivity(ActivityInterface $activity, array $options = []): void
    {
        $params = \array_merge($options, [
            'name' => $activity->getName(),
        ]);

        $this->extend(self::PARAM_ACTIVITIES, $params);
    }
}
