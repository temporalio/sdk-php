<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Fixture\ServiceImplInstance;

use Temporal\Tests\Nexus\Fixture\Service\VoidServiceInterface;

/**
 * Implementation with a non-operation helper method — the factory should ignore it.
 */
final class ServiceImplWithExtraNonOperationMethod implements VoidServiceInterface
{
    public function operation(): void {}

    public function plainHelper(): int
    {
        return 42;
    }
}
