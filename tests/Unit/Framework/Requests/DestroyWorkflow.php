<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Framework\Requests;

use Temporal\Worker\Transport\Command\Server\ServerRequest;
use Temporal\Worker\Transport\Command\Server\TickInfo;

/**
 * @internal
 */
final class DestroyWorkflow extends ServerRequest
{
    public function __construct(string $runId)
    {
        parent::__construct('DestroyWorkflow', new TickInfo(new \DateTimeImmutable()), ['runId' => $runId]);
    }
}
