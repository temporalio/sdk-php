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

#[Service(name: 'invalid-async-return-type-service')]
interface InvalidAsyncReturnTypeService
{
    #[AsyncOperation]
    public function badReturn(string $input): string;
}
