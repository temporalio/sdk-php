<?php

declare(strict_types=1);

namespace Temporal\Internal\Transport\Request;

use Temporal\Worker\Transport\Command\Client\Request;

/**
 * Non-blocking probe for the terminal state of an async caller-side Nexus
 * operation. The `id` field is the original {@see ExecuteNexusOperation}
 * message ID returned to the workflow.
 *
 * Response shape:
 *  - empty payloads → operation still in flight; caller should retry.
 *  - non-empty payloads → operation completed; payload is the result.
 *  - failure → operation completed with an error.
 *
 * Sync operations never need this — their result lands on the original
 * `ExecuteNexusOperation` response.
 *
 * @psalm-immutable
 */
final class GetNexusOperationResult extends Request
{
    public const NAME = 'GetNexusOperationResult';

    public function __construct(int $id)
    {
        parent::__construct(self::NAME, ['id' => $id]);
    }
}
