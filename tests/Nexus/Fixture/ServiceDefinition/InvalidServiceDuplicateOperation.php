<?php

/**
 * This file is part of Nexus RPC SDK for PHP package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Fixture\ServiceDefinition;

use Temporal\Nexus\Attribute\Operation;
use Temporal\Nexus\Attribute\Service;

#[Service]
interface InvalidServiceDuplicateOperation
{
    #[Operation]
    public function duplicateWhenNameOverridden1(): void;

    #[Operation(name: 'duplicateWhenNameOverridden1')]
    public function duplicateWhenNameOverridden2(): void;
}
