<?php

declare(strict_types=1);

namespace Temporal\Testing;

use Temporal\DataConverter\ValuesInterface;
use Temporal\Interceptor\Trait\WorkflowClientCallsInterceptorTrait;
use Temporal\Interceptor\WorkflowClient\GetResultInput;
use Temporal\Interceptor\WorkflowClientCallsInterceptor;

final class TimeLockingInterceptor implements WorkflowClientCallsInterceptor
{
    use WorkflowClientCallsInterceptorTrait;

    public function __construct(
        private readonly TestService $testService,
    ) {}

    public function getResult(GetResultInput $input, callable $next): ?ValuesInterface
    {
        $this->testService->unlockTimeSkipping();

        try {
            return $next($input);
        } finally {
            $this->testService->lockTimeSkipping();
        }
    }
}
