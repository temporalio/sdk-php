<?php

declare(strict_types=1);

namespace Temporal\Internal\Transport\Router;

use Nexus\Sdk\Handler\HandlerInputContent;
use Nexus\Sdk\Handler\OperationContext;
use Nexus\Sdk\Handler\OperationStartDetails;
use Nexus\Sdk\Link;
use React\Promise\Deferred;
use Temporal\DataConverter\EncodedValues;
use Temporal\Internal\Nexus\NexusHandlerErrorException;
use Temporal\Internal\Nexus\NexusTaskHandler;
use Temporal\Worker\Transport\Command\ServerRequestInterface;

/**
 * Handles "InvokeNexusOperation" command from Go RoadRunner plugin.
 *
 * Go sends: options={service, operation, requestId, callback, callbackHeaders, headers}
 *           payloads=binary input payload
 * PHP responds: payloads=result payload (sync) or failure
 */
final class InvokeNexusOperation extends Route
{
    public function __construct(
        private readonly NexusTaskHandler $taskHandler,
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

        // Build links from options if present
        $links = [];

        $context = OperationContext::create(
            service: $service,
            operation: $operation,
            headers: $requestHeaders,
        );

        $details = new OperationStartDetails(
            requestId: $requestId,
            callbackUrl: $callback !== '' ? $callback : null,
            callbackHeaders: $callbackHeaders,
            links: $links,
        );

        // Extract input from payloads — pass raw payload data + metadata to handler
        $inputData = '';
        $inputHeaders = [];
        $payloads = $request->getPayloads();
        if ($payloads instanceof \Temporal\DataConverter\EncodedValues) {
            $protoPayloads = $payloads->toPayloads();
            if ($protoPayloads !== null && $protoPayloads->getPayloads()->count() > 0) {
                $firstPayload = $protoPayloads->getPayloads()[0];
                $inputData = $firstPayload->getData();
                foreach ($firstPayload->getMetadata() as $k => $v) {
                    $inputHeaders[(string) $k] = (string) $v;
                }
            }
        }
        $input = new HandlerInputContent($inputData, $inputHeaders);

        try {
            $result = $this->taskHandler->startOperationDirect($context, $details, $input);
            $resolver->resolve($result);
        } catch (NexusHandlerErrorException $e) {
            $resolver->reject($e);
        } catch (\Throwable $e) {
            $resolver->reject($e);
        }
    }
}
