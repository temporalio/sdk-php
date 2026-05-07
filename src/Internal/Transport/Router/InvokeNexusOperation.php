<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Transport\Router;

use React\Promise\Deferred;
use Temporal\Api\Common\V1\Payload;
use Temporal\Api\Common\V1\Payloads;
use Temporal\Api\Nexus\V1\Request;
use Temporal\Api\Nexus\V1\StartOperationRequest;
use Temporal\Api\Nexus\V1\UnsuccessfulOperationError;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Internal\Nexus\NexusHandlerErrorException;
use Temporal\Internal\Nexus\NexusInvocationRegistry;
use Temporal\Internal\Nexus\NexusLinkConverter;
use Temporal\Internal\Nexus\NexusTaskHandler;
use Temporal\Nexus\Exception\OperationException;
use Temporal\Nexus\Handler\MethodCanceller;
use Temporal\Nexus\LinkParser;
use Temporal\Nexus\OperationState;
use Temporal\Worker\Transport\Command\Client\NexusOperationStarted;
use Temporal\Worker\Transport\Command\ServerRequestInterface;

final class InvokeNexusOperation extends Route
{
    public function __construct(
        private readonly NexusTaskHandler $taskHandler,
        private readonly NexusInvocationRegistry $invocations,
        private readonly DataConverterInterface $dataConverter,
    ) {}

    public function handle(ServerRequestInterface $request, array $headers, Deferred $resolver): void
    {
        $options = $request->getOptions();
        $invocationId = (int) ($options['invocationId'] ?? 0);

        $canceller = null;
        if ($invocationId !== 0) {
            $canceller = new MethodCanceller(NexusTaskHandler::deadlineFromHeaders($options['headers'] ?? []));
            $this->invocations->register($invocationId, $canceller);
        }

        try {
            $protoRequest = self::buildProtoRequest($options, $request->getPayloads());
            $response = $this->taskHandler->handleStartOperation($protoRequest, $canceller);

            $startResponse = $response->getStartOperation();
            \assert($startResponse !== null);

            if ($startResponse->hasSyncSuccess()) {
                $sync = $startResponse->getSyncSuccess();
                \assert($sync !== null);
                $resolver->resolve(new NexusOperationStarted(
                    async: false,
                    links: self::linksToWire($sync->getLinks()),
                    payloads: $this->payloadAsValues($sync->getPayload()),
                ));
                return;
            }

            if ($startResponse->hasAsyncSuccess()) {
                $async = $startResponse->getAsyncSuccess();
                \assert($async !== null);
                $resolver->resolve(new NexusOperationStarted(
                    async: true,
                    token: $async->getOperationToken(),
                    links: self::linksToWire($async->getLinks()),
                ));
                return;
            }

            \assert($startResponse->hasOperationError());
            $operationError = $startResponse->getOperationError();
            \assert($operationError !== null);
            $resolver->reject(self::operationErrorToException($operationError));
        } catch (NexusHandlerErrorException $e) {
            // Unwrap the proto-shaped wrapper produced by NexusTaskHandler — the
            // outbound FailureConverter switches on the typed HandlerException
            // (NexusHandlerException) to emit NexusHandlerFailureInfo on the wire.
            $resolver->reject($e->getPrevious() ?? $e);
        } catch (\Throwable $e) {
            $resolver->reject($e);
        } finally {
            if ($invocationId !== 0) {
                $this->invocations->unregister($invocationId);
            }
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function buildProtoRequest(array $options, ValuesInterface $payloads): Request
    {
        // Generate a fallback when the wire did not supply a requestId — Nexus
        // traffic always carries it; the fallback covers HTTP clients that
        // elide the header.
        $requestId = ($options['requestId'] ?? '') ?: \bin2hex(\random_bytes(8));

        $startRequest = (new StartOperationRequest())
            ->setService((string) ($options['service'] ?? ''))
            ->setOperation((string) ($options['operation'] ?? ''))
            ->setRequestId($requestId)
            ->setCallback((string) ($options['callback'] ?? ''))
            ->setCallbackHeader((array) ($options['callbackHeaders'] ?? []))
            ->setLinks(NexusLinkConverter::toNexusProtoLinks(
                LinkParser::fromRaw($options['links'] ?? null),
            ));

        $inputPayload = self::firstPayload($payloads);
        if ($inputPayload !== null) {
            $startRequest->setPayload($inputPayload);
        }

        return (new Request())
            ->setHeader((array) ($options['headers'] ?? []))
            ->setStartOperation($startRequest);
    }

    private static function firstPayload(ValuesInterface $values): ?Payload
    {
        $payloads = $values->toPayloads()->getPayloads();
        if ($payloads->count() === 0) {
            return null;
        }
        return $payloads[0];
    }

    /**
     * @param iterable<\Temporal\Api\Nexus\V1\Link> $links
     * @return list<array{url: string, type: string}>
     */
    private static function linksToWire(iterable $links): array
    {
        $out = [];
        foreach ($links as $link) {
            $out[] = ['url' => $link->getUrl(), 'type' => $link->getType()];
        }
        return $out;
    }

    private static function operationErrorToException(UnsuccessfulOperationError $error): OperationException
    {
        $message = $error->getFailure()?->getMessage() ?? '';
        $state = OperationState::tryFrom($error->getOperationState()) ?? OperationState::Failed;

        return $state === OperationState::Canceled
            ? OperationException::canceled($message)
            : OperationException::failed($message);
    }

    private function payloadAsValues(?Payload $payload): ValuesInterface
    {
        $payloads = new Payloads();
        if ($payload !== null) {
            $payloads->setPayloads([$payload]);
        }
        return EncodedValues::fromPayloads($payloads, $this->dataConverter);
    }
}
