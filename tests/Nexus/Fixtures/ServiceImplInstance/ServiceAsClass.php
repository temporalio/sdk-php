<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Fixtures\ServiceImplInstance;

use Temporal\Nexus\Attribute\Operation;
use Temporal\Nexus\Attribute\Service;

/**
 * Service implementation that carries `#[Service]` directly on the class — no separate
 * contract interface is required.
 */
#[Service]
final class ServiceAsClass
{
    #[Operation]
    public function classOperation(string $input): string
    {
        return $input;
    }
}
