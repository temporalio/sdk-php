<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\DataConverter\ExternalStorage\Stub;

use Temporal\Api\Common\V1\Payload;
use Temporal\DataConverter\ExternalStorage\StorageDriverClaim;
use Temporal\DataConverter\ExternalStorage\StorageDriverLimiterInterface;

final class CountingLimiter implements StorageDriverLimiterInterface
{
    public int $permits = 0;

    public function permit(Payload|StorageDriverClaim $item, \Closure $operation): mixed
    {
        ++$this->permits;

        return $operation();
    }
}
