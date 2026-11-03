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

class ActivityFailedException extends TemporalException
{
    public function __construct(
        public readonly string $activityId,
        public readonly ?string $runId,
        public readonly int $retryState,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            self::buildMessage([
                'activityId' => $activityId,
                'runId' => $runId,
                'retryState' => $retryState,
            ]),
            0,
            $previous,
        );
    }
}
