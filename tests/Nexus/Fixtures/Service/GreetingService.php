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
use Temporal\Nexus\Attribute\OperationCancel;
use Temporal\Nexus\Link;
use Temporal\Nexus\Nexus;
use Temporal\Nexus\WorkflowHandle;

final class GreetingService implements GreetingServiceInterface
{
    public const WORKFLOW_ID = 'greeting-workflow';

    /** @var callable(string): string */
    private $apiClient;

    public function __construct(callable $apiClient)
    {
        $this->apiClient = $apiClient;
    }

    public function sayHello1(string $name): string
    {
        return "Hello, {$name}!";
    }

    public function sayHello2(string $name): WorkflowHandle
    {
        $details = Nexus::getStartDetails();
        if ($details->callbackUrl !== null) {
            throw new \InvalidArgumentException('This service does not support callbacks');
        }

        ($this->apiClient)($name);

        if (\str_ends_with($name, 'link')) {
            Nexus::getCurrentOperationContext()->links->add(
                new Link('http://somepath?k=v', 'com.example.MyResource'),
            );
        }

        return WorkflowHandle::fromWorkflowMethod(
            FakeGreetingWorkflow::class,
            WorkflowOptions::new()->withWorkflowId(self::WORKFLOW_ID),
        );
    }

    #[OperationCancel(operation: 'sayHello2')]
    public function cancelSayHello2(string $token): void {}
}
