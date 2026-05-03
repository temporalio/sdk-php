<?php

declare(strict_types=1);

namespace Temporal\Internal\Transport\Request;

use Temporal\Worker\Transport\Command\Client\Request;

/**
 * Fire-and-forget cleanup signal sent by the caller-side Nexus polling loop
 * when its workflow scope is cancelled. Tells RoadRunner to drop the
 * bookkeeping entry for the in-flight operation identified by `id`
 * (the original {@see ExecuteNexusOperation} message ID) instead of waiting
 * for workflow shutdown.
 *
 * @psalm-immutable
 */
final class CancelNexusOperationResult extends Request
{
    public const NAME = 'CancelNexusOperationResult';

    public function __construct(int $id)
    {
        parent::__construct(self::NAME, ['id' => $id]);
    }
}
