<?php

declare(strict_types=1);

namespace Temporal\Internal\Transport\Router;

use Nexus\Sdk\Handler\HandlerInputContent;
use Nexus\Sdk\Handler\MethodCanceller;
use Nexus\Sdk\Handler\OperationContext;
use Nexus\Sdk\Handler\OperationStartDetails;
use Nexus\Sdk\LinkParser;
use React\Promise\Deferred;
use Temporal\DataConverter\EncodedValues;
use Temporal\Internal\Nexus\NexusInvocationRegistry;
use Temporal\Internal\Nexus\NexusTaskHandler;
use Temporal\Worker\Transport\Command\ServerRequestInterface;

/**
 * Route for "InvokeNexusOperation" from RR.
 * Registers a MethodCanceller when invocationId is supplied; cleans up in finally.
 */
final class InvokeNexusOperation extends Route
{
    public function __construct(
        private readonly NexusTaskHandler $taskHandler,
        private readonly NexusInvocationRegistry $invocations,
    ) {}

    public function handle(ServerRequestInterface $request, array $headers, Deferred $resolver): void
    {
        $options = $request->getOptions();

        $service = $options['service'] ?? '';
        $operation = $options['operation'] ?? '';
        $requestId = $options['requestId'] ?? '';
        $callback = $options['callback'] ?? null;
        $callbackHeaders = $options['callbackHeaders'] ?? [];
        $requestHeaders = $options['headers'] ?? [];
        // 0 = no canceller (legacy RR).
        $invocationId = (int) ($options['invocationId'] ?? 0);

        $deadline = NexusTaskHandler::deadlineFromHeaders($requestHeaders);

        $canceller = null;
        if ($invocationId !== 0) {
            // Canceller fires listeners on deadline expiry too (Java parity).
            $canceller = new MethodCanceller($deadline);
            $this->invocations->register($invocationId, $canceller);
        }

        $context = new OperationContext(
            service: $service,
            operation: $operation,
            headers: $requestHeaders,
            deadline: $deadline,
            methodCanceller: $canceller,
        );

        $inputData = '';
        $inputHeaders = [];
        $payloads = $request->getPayloads();
        if ($payloads instanceof EncodedValues) {
            $protoPayloads = $payloads->toPayloads();
            if ($protoPayloads->getPayloads()->count() > 0) {
                $firstPayload = $protoPayloads->getPayloads()[0];
                $inputData = $firstPayload->getData();
                foreach ($firstPayload->getMetadata() as $k => $v) {
                    $inputHeaders[(string) $k] = (string) $v;
                }
            }
        }
        $input = new HandlerInputContent($inputData, $inputHeaders);

        try {
            // Strict link parsing: malformed → BadRequest (Java parity).
            $links = LinkParser::fromRaw($options['links'] ?? null);

            $details = new OperationStartDetails(
                requestId: $requestId,
                callbackUrl: $callback ?: null,
                callbackHeaders: $callbackHeaders,
                links: $links,
            );

            $result = $this->taskHandler->startOperationDirect($context, $details, $input);
            $resolver->resolve($result);
        } catch (\Throwable $e) {
            $resolver->reject($e);
        } finally {
            if ($invocationId !== 0) {
                $this->invocations->unregister($invocationId);
            }
        }
    }
}
