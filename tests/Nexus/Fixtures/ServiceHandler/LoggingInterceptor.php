<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Fixtures\ServiceHandler;

use Temporal\Interceptor\NexusOperationInbound\CancelOperationInput;
use Temporal\Interceptor\NexusOperationInbound\StartOperationInput;
use Temporal\Interceptor\NexusOperationInboundCallsInterceptor;
use Temporal\Nexus\Handler\OperationStartResult;

/**
 * Test interceptor that records each operation it sees.
 */
final class LoggingInterceptor implements NexusOperationInboundCallsInterceptor
{
    /** @var list<string> */
    private array $operations = [];

    /**
     * @return list<string>
     */
    public function getOperations(): array
    {
        return $this->operations;
    }

    public function startOperation(StartOperationInput $input, callable $next): OperationStartResult
    {
        $this->operations[] = $input->operationContext->operation;
        return $next($input);
    }

    public function cancelOperation(CancelOperationInput $input, callable $next): void
    {
        $this->operations[] = $input->operationContext->operation;
        $next($input);
    }
}
