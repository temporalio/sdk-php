<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Transport\Request;

use Temporal\Worker\Transport\Command\Client\Request;

/**
 * Listen-and-wait probe for the start envelope of a caller-side Nexus
 * operation. The `id` field is the original {@see ExecuteNexusOperation}
 * message ID returned to the workflow.
 *
 * RR registers the request as a listener on its `nexusStartedRegistry`; the
 * response is pushed when the SDK's started callback fires (handler ack'd
 * the start) — exactly the same pattern as
 * {@see GetChildWorkflowExecution} for child workflows.
 *
 * Response: a single payload carrying the JSON-encoded
 * {@see \Temporal\Internal\Workflow\NexusStartEnvelope} (`{async, token?}`).
 *
 * @psalm-immutable
 */
final class GetNexusOperationStarted extends Request
{
    public const NAME = 'GetNexusOperationStarted';

    public function __construct(int $id)
    {
        parent::__construct(self::NAME, ['id' => $id]);
    }
}
