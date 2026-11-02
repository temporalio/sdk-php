<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Exception\Client;

use Temporal\Exception\TemporalException;

class ActivityAlreadyStartedException extends TemporalException
{
    public function __construct(
        public readonly string $activityId,
        public readonly string $activityType,
        public readonly ?string $runId,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            self::buildMessage([
                'activityId' => $activityId,
                'activityType' => $activityType,
                'runId' => $runId,
            ]),
            0,
            $previous,
        );
    }
}
