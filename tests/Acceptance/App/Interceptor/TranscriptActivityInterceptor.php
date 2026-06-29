<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Interceptor;

use Temporal\Activity;
use Temporal\Interceptor\ActivityInbound\ActivityInput;
use Temporal\Interceptor\ActivityInboundInterceptor;
use Temporal\Interceptor\Trait\ActivityInboundInterceptorTrait;
use Temporal\Tests\Acceptance\App\Logger\TranscriptWriter;
use Temporal\Tests\Acceptance\App\Runtime\ContainerFacade;

final class TranscriptActivityInterceptor implements ActivityInboundInterceptor
{
    use ActivityInboundInterceptorTrait;

    private ?TranscriptWriter $writer = null;

    public function handleActivityInbound(ActivityInput $input, callable $next): mixed
    {
        $writer = $this->resolveWriter();
        $attributes = $this->buildAttributes();
        $writer?->writeMeta('activity_start', $attributes);
        try {
            $result = $next($input);
            $writer?->writeMeta('activity_completed', $attributes);
            return $result;
        } catch (\Throwable $exception) {
            $writer?->writeException('activity_throw', $attributes, $exception);
            throw $exception;
        }
    }

    /**
     * @return array<string, scalar|null>
     */
    private function buildAttributes(): array
    {
        try {
            $info = Activity::getInfo();
            return [
                'name' => $info->type->name,
                'attempt' => $info->attempt,
                'activity_id' => $info->id,
                'workflow_id' => $info->workflowExecution->getID(),
                'run_id' => $info->workflowExecution->getRunID(),
            ];
        } catch (\Throwable) {
            return ['name' => 'unknown', 'attempt' => 0];
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
