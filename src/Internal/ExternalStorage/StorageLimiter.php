<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\ExternalStorage;

use Temporal\Api\Common\V1\Payload;
use Temporal\DataConverter\ExternalStorage\StorageDriverClaim;
use Temporal\DataConverter\ExternalStorage\StorageDriverLimiterInterface;

/**
 * @internal
 */
final class StorageLimiter implements StorageDriverLimiterInterface
{
    private bool $used = false;

    public function __construct(
        private readonly StorageBudget $messageBudget,
        private readonly StorageBudget $sharedBudget,
    ) {}

    public function permit(Payload|StorageDriverClaim $item, \Closure $operation): mixed
    {
        $this->used = true;
        $this->messageBudget->acquire();
        try {
            $this->sharedBudget->acquire();
            try {
                return $operation();
            } finally {
                $this->sharedBudget->release();
            }
        } finally {
            $this->messageBudget->release();
        }
    }

    public function wasUsed(): bool
    {
        return $this->used;
    }
}
