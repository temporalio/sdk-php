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

#[Service(name: 'invalid-service-with-operations')]
interface InvalidServiceWithOperations
{
    public function methodWithNoAnnotation(): void;

    #[Operation]
    public function twoParameterOperation(string $one, string $two): void;

    #[Operation]
    public static function staticOperation(): void;
}
