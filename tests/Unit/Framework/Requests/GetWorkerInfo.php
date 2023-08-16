<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Framework\Requests;

use Temporal\Worker\Transport\Command\ServerRequest;

/**
 * @internal
 */
final class GetWorkerInfo extends ServerRequest
{
    public function __construct()
    {
        parent::__construct('GetWorkerInfo');
    }
}
