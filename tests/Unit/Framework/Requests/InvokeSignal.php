<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Framework\Requests;

use Temporal\DataConverter\EncodedValues;
use Temporal\Worker\Transport\Command\ServerRequest;

/**
 * @internal
 */
final class InvokeSignal extends ServerRequest
{
    public function __construct(string $runId, string $name, ...$args)
    {
        parent::__construct(
            'InvokeSignal',
            [
                'runId' => $runId,
                'name' => $name,
            ],
            EncodedValues::fromValues($args)
        );
    }
}
