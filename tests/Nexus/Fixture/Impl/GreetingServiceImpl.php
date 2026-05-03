<?php

/**
 * This file is part of Nexus RPC SDK for PHP package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Fixture\Impl;

use Temporal\Nexus\Attribute\OperationImpl;
use Temporal\Nexus\Attribute\ServiceImpl;
use Temporal\Nexus\Exception\ErrorType;
use Temporal\Nexus\Exception\HandlerException;
use Temporal\Nexus\Handler\OperationCancelDetails;
use Temporal\Nexus\Handler\OperationContext;
use Temporal\Nexus\Handler\OperationHandlerInterface;
use Temporal\Nexus\Handler\OperationStartDetails;
use Temporal\Nexus\Handler\OperationStartResult;
use Temporal\Nexus\Handler\SynchronousOperationHandler;
use Temporal\Nexus\Link;
use Temporal\Nexus\OperationInfo;
use Temporal\Nexus\OperationState;
use Temporal\Tests\Nexus\Fixture\Service\GreetingServiceInterface;

#[ServiceImpl(service: GreetingServiceInterface::class)]
final class GreetingServiceImpl
{
    /** @var callable(string): string */
    private $apiClient;

    public function __construct(callable $apiClient)
    {
        $this->apiClient = $apiClient;
    }

    #[OperationImpl]
    public function sayHello1(): OperationHandlerInterface
    {
        return new SynchronousOperationHandler(
            fn(OperationContext $ctx, OperationStartDetails $details, ?string $name): string
                => "Hello, {$name}!",
        );
    }

    #[OperationImpl]
    public function sayHello2(): OperationHandlerInterface
    {
        return new SayHello2Handler($this->apiClient);
    }
}

/**
 * @implements OperationHandlerInterface<string, string>
 */
final class SayHello2Handler implements OperationHandlerInterface
{
    /** @var array<string, string> */
    private array $operations = [];
    private $apiClient;

    public function __construct(callable $apiClient)
    {
        $this->apiClient = $apiClient;
    }

    public function start(
        OperationContext $context,
        OperationStartDetails $details,
        mixed $param,
    ): OperationStartResult {
        $name = $param ?? '<unknown>';

        // Sync path for names starting with "sync-"
        if (\str_starts_with($name, 'sync-')) {
            return OperationStartResult::sync("Hello, {$name}!");
        }

        if ($details->callbackUrl !== null) {
            throw new \InvalidArgumentException('This service does not support callbacks');
        }

        $id = \bin2hex(\random_bytes(16));
        $this->operations[$id] = ($this->apiClient)($name);

        $info = new OperationInfo($id, OperationState::Running);

        // Add link for names ending with "link"
        if (\str_ends_with($name, 'link')) {
            $context->links->add(new Link('http://somepath?k=v', 'com.example.MyResource'));
            return OperationStartResult::async($info);
        }

        return OperationStartResult::async($info);
    }

    public function cancel(
        OperationContext $context,
        OperationCancelDetails $details,
    ): void {
        if (!isset($this->operations[$details->operationToken])) {
            throw HandlerException::create(
                ErrorType::NotFound,
                "Operation not found for ID: {$details->operationToken}",
            );
        }
        unset($this->operations[$details->operationToken]);
    }

    public static function sync(callable $function): OperationHandlerInterface
    {
        return new SynchronousOperationHandler($function);
    }
}
