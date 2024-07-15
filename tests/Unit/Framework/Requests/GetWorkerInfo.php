<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Framework\Requests;

use DateTimeImmutable;
use Temporal\Worker\Transport\Command\Server\ServerRequest;
use Temporal\Worker\Transport\Command\Server\TickInfo;

/**
 * @internal
 */
final class GetWorkerInfo extends ServerRequest
{
    public function __construct()
    {
        parent::__construct('GetWorkerInfo', new TickInfo(new DateTimeImmutable()));
    }
}
