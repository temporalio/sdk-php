<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Fixtures\ServiceDefinition;

use Temporal\Nexus\Attribute\AsyncOperation;
use Temporal\Nexus\Attribute\Service;
use Temporal\Nexus\WorkflowHandle;

#[Service(name: 'nullable-async-return-type-service')]
interface NullableAsyncReturnTypeService
{
    #[AsyncOperation]
    public function badNullableReturn(string $input): ?WorkflowHandle;
}
