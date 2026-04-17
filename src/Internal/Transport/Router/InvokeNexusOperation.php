<?php

declare(strict_types=1);

namespace Temporal\Internal\Transport\Router;

use Nexus\Sdk\Handler\HandlerInputContent;
use Nexus\Sdk\Handler\MethodCanceller;
use Nexus\Sdk\Handler\OperationContext;
use Nexus\Sdk\Handler\OperationStartDetails;
use Nexus\Sdk\Link;
use React\Promise\Deferred;
use Temporal\Internal\Nexus\NexusHandlerErrorException;
use Temporal\Internal\Nexus\NexusInvocationRegistry;
use Temporal\Internal\Nexus\NexusTaskHandler;
use Temporal\Worker\Transport\Command\ServerRequestInterface;

/**
 * Handles "InvokeNexusOperation" command from Go RoadRunner plugin.
 *
 * Go sends: options={service, operation, requestId, callback, callbackHeaders, headers, links, invocationId?}
 *           payloads=binary input payload
 * PHP responds: payloads=result payload (sync) or failure
 *
 * When `invocationId` is present (non-zero) a fresh {@see MethodCanceller}
 * is created and registered in {@see NexusInvocationRegistry} so the
 * `CancelNexusOperationMethod` route can trigger it. The canceller is
 * removed in a `finally` block regardless of outcome.
 *
 * The `invocationId` key is optional so older RoadRunner builds keep
 * working — the context simply gets no canceller and
 * `$context->isMethodCancelled()` stays `false`.
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
        // Correlates this invocation with a future CancelNexusOperationMethod.
        // `0` means the RR side did not supply one — method-cancel is then a
        // silent no-op, preserving compatibility with older RR builds.
        $invocationId = (int) ($options['invocationId'] ?? 0);

        // Caller-side Nexus-Link headers parsed by RR and passed as structured
        // `{url, type}` objects. Malformed entries are skipped.
        $links = self::parseLinks($options['links'] ?? []);

        $deadline = NexusTaskHandler::deadlineFromHeaders($requestHeaders);

        $canceller = null;
        if ($invocationId !== 0) {
            // Passing the deadline to the canceller gives listeners the
            // chance to fire on expiry (Java parity). The context-level
            // deadline check in OperationContext::isMethodCancelled() is a
            // backup for the no-canceller case.
            $canceller = new MethodCanceller($deadline);
            $this->invocations->register($invocationId, $canceller);
        }

        $context = OperationContext::create(
            service: $service,
            operation: $operation,
            headers: $requestHeaders,
            deadline: $deadline,
            methodCanceller: $canceller,
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
        } finally {
            if ($invocationId !== 0) {
                $this->invocations->unregister($invocationId);
            }
        }
    }

    /**
     * Turn a raw JSON-decoded array of `{url, type}` entries into `Link[]`.
     *
     * Entries missing either field, or with empty values, are skipped. A
     * non-array input returns an empty list.
     *
     * @param mixed $raw
     * @return Link[]
     */
    private static function parseLinks(mixed $raw): array
    {
        if (!\is_array($raw)) {
            return [];
        }

        $links = [];
        foreach ($raw as $entry) {
            if (!\is_array($entry)) {
                continue;
            }
            $url = (string) ($entry['url'] ?? '');
            $type = (string) ($entry['type'] ?? '');
            if ($url === '' || $type === '') {
                continue;
            }
            $links[] = new Link($url, $type);
        }
        return $links;
    }
}
