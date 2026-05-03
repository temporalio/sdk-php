<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Fixture\Impl;

use Temporal\Nexus\Attribute\OperationCancel;
use Temporal\Nexus\OperationInfo;
use Temporal\Nexus\OperationState;
use Temporal\Tests\Nexus\Fixture\Service\GreetingServiceInterface;

/**
 * Greeting-service implementation whose operations either trigger a configured throwable
 * (for testing exception paths) or return a benign sync stub.
 */
final class ThrowingGreetingImpl implements GreetingServiceInterface
{
    public function __construct(
        private readonly ?\Throwable $hello1Throw = null,
        private readonly ?\Throwable $hello2Throw = null,
    ) {}

    public function sayHello1(string $name): string
    {
        if ($this->hello1Throw !== null) {
            throw $this->hello1Throw;
        }
        return 'ok';
    }

    public function sayHello2(string $name): OperationInfo
    {
        if ($this->hello2Throw !== null) {
            throw $this->hello2Throw;
        }
        return new OperationInfo('throwing-token', OperationState::Running);
    }

    #[OperationCancel(operation: 'sayHello2')]
    public function cancelSayHello2(string $token): void {}
}
