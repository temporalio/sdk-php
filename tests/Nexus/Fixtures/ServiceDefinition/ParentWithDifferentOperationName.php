<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Fixtures\ServiceDefinition;

use Temporal\Nexus\Attribute\Operation;
use Temporal\Nexus\Attribute\Service;

#[Service(name: 'OperationOverrideMismatch')]
interface ParentWithDifferentOperationName
{
    #[Operation(name: 'parent-name')]
    public function sharedMethod(): void;
}
