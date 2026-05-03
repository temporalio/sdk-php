<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Fixture\Service;

use Temporal\Nexus\Attribute\Operation;
use Temporal\Nexus\Attribute\Service;

#[Service]
interface GreetingServiceInterface
{
    #[Operation]
    public function sayHello1(string $name): string;

    #[Operation]
    public function sayHello2(string $name): string;
}
