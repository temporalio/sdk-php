<?php

declare(strict_types=1);

namespace Temporal\Testing\Transcript;

use Temporal\Activity;
use Temporal\Interceptor\ActivityInbound\ActivityInput;
use Temporal\Interceptor\ActivityInboundInterceptor;
use Temporal\Interceptor\Trait\ActivityInboundInterceptorTrait;
use Temporal\Testing\Transcript\TranscriptWriter;

final class TranscriptActivityInterceptor implements ActivityInboundInterceptor
{
    use ActivityInboundInterceptorTrait;

    public function __construct(
        private readonly TranscriptWriter $transcript,
    ) {}

    public function handleActivityInbound(ActivityInput $input, callable $next): mixed
    {
        $attributes = $this->buildAttributes();
        $this->transcript->writeMeta('activity_start', $attributes);
        try {
            $result = $next($input);
            $this->transcript->writeMeta('activity_completed', $attributes);
            return $result;
        } catch (\Throwable $exception) {
            $this->transcript->writeException('activity_throw', $attributes, $exception);
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
}
