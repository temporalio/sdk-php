<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client;

use Temporal\Client\Activity\ActivityExecutionInfo;
use Temporal\Client\Activity\ActivityHandle;
use Temporal\Client\Activity\CountActivityExecutions;
use Temporal\Client\Activity\StartActivityOptions;
use Temporal\Client\Common\ClientContextInterface;
use Temporal\Client\Common\Paginator;
use Temporal\Exception\Client\ActivityAlreadyStartedException;

interface ActivityClientInterface extends ClientContextInterface
{
    /**
     * @param non-empty-string $activityType
     *
     * @throws ActivityAlreadyStartedException
     */
    public function start(string $activityType, StartActivityOptions $options, mixed ...$args): ActivityHandle;

    /**
     * @param non-empty-string $activityId
     * @param non-empty-string|null $namespace
     */
    public function getHandle(string $activityId, ?string $runId = null, ?string $namespace = null): ActivityHandle;

    /**
     * @param non-empty-string|null $namespace
     *
     * @return Paginator<ActivityExecutionInfo>
     */
    public function listExecutions(string $query, ?string $namespace = null, int $pageSize = 10): Paginator;

    /**
     * @param non-empty-string|null $namespace
     */
    public function countExecutions(string $query, ?string $namespace = null): CountActivityExecutions;

    public function newActivityCompletionClient(): ActivityCompletionClientInterface;
}
