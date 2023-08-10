<?php

declare(strict_types=1);

namespace Temporal\Testing\Replay\Exception;

/**
 * Exception thrown during workflow replay.
 *
 * @link https://docs.temporal.io/workflows/#deterministic-constraints
 */
final class NonDeterministicWorkflowException extends ReplayerException
{
}
