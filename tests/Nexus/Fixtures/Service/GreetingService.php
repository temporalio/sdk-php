<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Fixtures\Service;

use Temporal\Common\Uuid;
use Temporal\Nexus\Attribute\OperationCancel;
use Temporal\Nexus\Exception\ErrorType;
use Temporal\Nexus\Exception\HandlerException;
use Temporal\Nexus\Link;
use Temporal\Nexus\Nexus;
use Temporal\Nexus\OperationInfo;
use Temporal\Nexus\OperationState;

final class GreetingService implements GreetingServiceInterface
{
    /** @var array<string, string> */
    private array $operations = [];

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

    public function sayHello2(string $name): OperationInfo
    {
        $details = Nexus::getStartDetails();
        if ($details->callbackUrl !== null) {
            throw new \InvalidArgumentException('This service does not support callbacks');
        }

        $id = Uuid::v4();
        $this->operations[$id] = ($this->apiClient)($name);

        // Add link for names ending with "link"
        if (\str_ends_with($name, 'link')) {
            Nexus::getCurrentOperationContext()->links->add(
                new Link('http://somepath?k=v', 'com.example.MyResource'),
            );
        }

        return new OperationInfo($id, OperationState::Running);
    }

    #[OperationCancel(operation: 'sayHello2')]
    public function cancelSayHello2(string $token): void
    {
        if (!isset($this->operations[$token])) {
            throw HandlerException::create(
                ErrorType::NotFound,
                "Operation not found for ID: {$token}",
            );
        }
        unset($this->operations[$token]);
    }
}
