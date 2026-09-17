<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Fixtures\Service;

use Temporal\Client\WorkflowOptions;
use Temporal\Nexus\WorkflowHandle;

/**
 * Greeting-service implementation whose operations either trigger a configured throwable
 * (for testing exception paths) or return a benign workflow-backed stub.
 */
final class ThrowingGreetingService implements GreetingServiceInterface
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

    public function sayHello2(string $name): WorkflowHandle
    {
        if ($this->hello2Throw !== null) {
            throw $this->hello2Throw;
        }
        return WorkflowHandle::fromWorkflowMethod(
            FakeGreetingWorkflow::class,
            WorkflowOptions::new()->withWorkflowId('throwing-workflow'),
        );
    }

}
