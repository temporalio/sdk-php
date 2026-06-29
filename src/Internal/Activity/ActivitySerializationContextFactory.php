<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Activity;

use Temporal\Activity\ActivityInfo;
use Temporal\DataConverter\ActivitySerializationContext;

final class ActivitySerializationContextFactory
{
    public static function fromActivityInfo(ActivityInfo $info, bool $isLocal): ActivitySerializationContext
    {
        return new ActivitySerializationContext(
            namespace: $info->workflowNamespace,
            workflowId: $info->workflowExecution?->getID(),
            workflowType: $info->workflowType?->name,
            activityType: $info->type->name,
            taskQueue: $info->taskQueue,
            isLocal: $isLocal,
        );
    }
}
