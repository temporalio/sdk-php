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
use Temporal\Tests\Acceptance\App\Runtime\ContainerFacade;

final class TranscriptWorkflowInterceptor implements WorkflowInboundCallsInterceptor
{
    use WorkflowInboundCallsInterceptorTrait;

    private ?TranscriptWriter $writer = null;

    public function execute(WorkflowInput $input, callable $next): void
    {
        $attributes = [
            'workflow_type' => $input->info->type->name,
            'workflow_id' => $input->info->execution->getID(),
            'run_id' => $input->info->execution->getRunID(),
            'is_replaying' => $input->isReplaying,
        ];
        $this->runPhase('workflow_execute', $attributes, static fn() => $next($input));
    }

    public function handleSignal(SignalInput $input, callable $next): void
    {
        $attributes = [
            'signal_name' => $input->signalName,
            'workflow_id' => $input->info->execution->getID(),
            'is_replaying' => $input->isReplaying,
        ];
        $this->runPhase('workflow_signal', $attributes, static fn() => $next($input));
    }

    public function handleQuery(QueryInput $input, callable $next): mixed
    {
        $attributes = [
            'query_name' => $input->queryName,
            'workflow_id' => $input->info->execution->getID(),
        ];
        return $this->runPhase('workflow_query', $attributes, static fn() => $next($input));
    }

    public function handleUpdate(UpdateInput $input, callable $next): mixed
    {
        $attributes = [
            'update_name' => $input->updateName,
            'update_id' => $input->updateId,
            'workflow_id' => $input->info->execution->getID(),
            'is_replaying' => $input->isReplaying,
        ];
        return $this->runPhase('workflow_update', $attributes, static fn() => $next($input));
    }

    public function validateUpdate(UpdateInput $input, callable $next): void
    {
        $attributes = [
            'update_name' => $input->updateName,
            'update_id' => $input->updateId,
            'workflow_id' => $input->info->execution->getID(),
            'is_replaying' => $input->isReplaying,
        ];
        $this->runPhase('workflow_validate_update', $attributes, static fn() => $next($input));
    }

    /**
     * @template T
     * @param array<string, scalar|null> $attributes
     * @param callable(): T $execution
     * @return T
     */
    private function runPhase(string $phase, array $attributes, callable $execution): mixed
    {
        $writer = $this->resolveWriter();
        $writer?->writeMeta($phase . '_start', $attributes);
        try {
            $result = $execution();
            $writer?->writeMeta($phase . '_completed', $attributes);
            return $result;
        } catch (\Throwable $exception) {
            $writer?->writeException($phase, $attributes, $exception);
            throw $exception;
        }
    }

    private function resolveWriter(): ?TranscriptWriter
    {
        if ($this->writer !== null) {
            return $this->writer;
        }
        try {
            $container = ContainerFacade::$container ?? null;
            if ($container !== null && $container->has(TranscriptWriter::class)) {
                $this->writer = $container->get(TranscriptWriter::class);
            }
        } catch (\Throwable) {
            // intentionally swallow
        }
        return $this->writer;
    }
}
