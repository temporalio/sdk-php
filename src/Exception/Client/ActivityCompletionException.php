<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Exception\Client;

use Temporal\Activity\ActivityInfo;
use Temporal\Exception\TemporalException;

class ActivityCompletionException extends TemporalException
{
    private ?string $workflowId = null;
    private ?string $runId = null;
    private ?string $activityType = null;
    private ?string $activityId = null;

    /**
     * @return static
     */
    public static function fromPrevious(\Throwable $e): self
    {
        return new static(
            $e->getMessage(),
            $e->getCode(),
            $e,
        );
    }

    /**
     * @return static
     */
    public static function fromPreviousWithActivityId(string $activityId, \Throwable $e): self
    {
        $e = new static('', $e->getCode(), $e);
        $e->activityId = $activityId;

        return $e;
    }

    /**
     * @return static
     */
    public static function fromActivityInfo(ActivityInfo $info, \Throwable $e = null): self
    {
        $e = new static(
            self::buildMessage(
                [
                    'workflowId' => $info->workflowExecution->getID(),
                    'runId' => $info->workflowExecution->getRunID(),
                    'activityId' => $info->id,
                    'activityType' => $info->type->name,
                ],
            ),
            $e === null ? 0 : $e->getCode(),
            $e,
        );

        $e->activityId = $info->id;
        $e->workflowId = $info->workflowExecution->getID();
        $e->runId = $info->workflowExecution->getRunID();
        $e->activityType = $info->type->name;

        return $e;
    }

    public function getWorkflowId(): ?string
    {
        return $this->workflowId;
    }

    public function getRunId(): ?string
    {
        return $this->runId;
    }

    public function getActivityType(): ?string
    {
        return $this->activityType;
    }

    public function getActivityId(): ?string
    {
        return $this->activityId;
    }
}
