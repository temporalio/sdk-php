<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Interceptor;

use Temporal\Interceptor\Trait\WorkflowInboundCallsInterceptorTrait;
use Temporal\Interceptor\WorkflowInbound\QueryInput;
use Temporal\Interceptor\WorkflowInbound\SignalInput;
use Temporal\Interceptor\WorkflowInbound\UpdateInput;
use Temporal\Interceptor\WorkflowInbound\WorkflowInput;
use Temporal\Interceptor\WorkflowInboundCallsInterceptor;
use Temporal\Tests\Acceptance\App\Logger\TranscriptWriter;

final class TranscriptWorkflowInterceptor implements WorkflowInboundCallsInterceptor
{
    use WorkflowInboundCallsInterceptorTrait;

    public function __construct(
        private readonly TranscriptWriter $transcript,
    ) {}

    public function execute(WorkflowInput $input, callable $next): void
    {
        $attributes = [
            'workflow_type' => $input->info->type->name,
            'workflow_id' => $input->info->execution->getID(),
            'run_id' => $input->info->execution->getRunID(),
            'is_replaying' => $input->isReplaying,
        ];
        $this->runPhase('workflow_execute', $attributes, fn() => $next($input));
    }

    public function handleSignal(SignalInput $input, callable $next): void
    {
        $attributes = [
            'signal_name' => $input->signalName,
            'workflow_id' => $input->info->execution->getID(),
            'is_replaying' => $input->isReplaying,
        ];
        $this->runPhase('workflow_signal', $attributes, fn() => $next($input));
    }

    public function handleQuery(QueryInput $input, callable $next): mixed
    {
        $attributes = [
            'query_name' => $input->queryName,
            'workflow_id' => $input->info->execution->getID(),
        ];
        return $this->runPhase('workflow_query', $attributes, fn() => $next($input));
    }

    public function handleUpdate(UpdateInput $input, callable $next): mixed
    {
        $attributes = [
            'update_name' => $input->updateName,
            'update_id' => $input->updateId,
            'workflow_id' => $input->info->execution->getID(),
            'is_replaying' => $input->isReplaying,
        ];
        return $this->runPhase('workflow_update', $attributes, fn() => $next($input));
    }

    public function validateUpdate(UpdateInput $input, callable $next): void
    {
        $next($input);
    }

    /**
     * @template T
     * @param array<string, scalar|null> $attributes
     * @param callable(): T $execution
     * @return T
     */
    private function runPhase(string $phase, array $attributes, callable $execution): mixed
    {
        $this->transcript->writeMeta($phase . '_start', $attributes);
        try {
            $result = $execution();
            $this->transcript->writeMeta($phase . '_completed', $attributes);
            return $result;
        } catch (\Throwable $exception) {
            $this->transcript->writeException($phase . '_failed', $attributes, $exception);
            throw $exception;
        }
    }
}
