<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Framework\Requests;

use Temporal\Worker\Transport\Command\Request;

/**
 * @internal
 */
final class GetWorkerInfo extends Request
{
    public function __construct()
    {
        parent::__construct('GetWorkerInfo',);
    }
}
