<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Interceptor;

use Temporal\Interceptor\NexusOperationInbound\NexusOperationCancelInput;
use Temporal\Interceptor\NexusOperationInbound\NexusOperationStartInput;
use Temporal\Interceptor\NexusOperationInboundCallsInterceptor;
use Temporal\Interceptor\Trait\NexusOperationInboundCallsInterceptorTrait;
use Temporal\Nexus\Handler\OperationStartResult;
use Temporal\Tests\Acceptance\App\Logger\TranscriptWriter;
use Temporal\Tests\Acceptance\App\Runtime\ContainerFacade;

final class TranscriptNexusInterceptor implements NexusOperationInboundCallsInterceptor
{
    use NexusOperationInboundCallsInterceptorTrait;

    private ?TranscriptWriter $writer = null;

    public function startNexusOperation(NexusOperationStartInput $input, callable $next): OperationStartResult
    {
        $writer = $this->resolveWriter();
        $attributes = [
            'service' => $input->context->service,
            'operation' => $input->context->operation,
            'request_id' => $input->details->requestId,
        ];
        $writer?->writeMeta('nexus_start_start', $attributes);
        try {
            $result = $next($input);
            $writer?->writeMeta('nexus_start_completed', $attributes);
            return $result;
        } catch (\Throwable $exception) {
            $writer?->writeException('nexus_start', $attributes, $exception);
            throw $exception;
        }
    }

    public function cancelNexusOperation(NexusOperationCancelInput $input, callable $next): void
    {
        $writer = $this->resolveWriter();
        $attributes = [
            'service' => $input->context->service,
            'operation' => $input->context->operation,
        ];
        $writer?->writeMeta('nexus_cancel_start', $attributes);
        try {
            $next($input);
            $writer?->writeMeta('nexus_cancel_completed', $attributes);
        } catch (\Throwable $exception) {
            $writer?->writeException('nexus_cancel', $attributes, $exception);
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
