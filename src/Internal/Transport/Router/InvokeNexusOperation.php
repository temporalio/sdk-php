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
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Nexus\NexusHandlerErrorException;
use Temporal\Internal\Nexus\NexusInvocationRegistry;
use Temporal\Internal\Nexus\NexusLinkConverter;
use Temporal\Internal\Nexus\NexusTaskHandler;
use Temporal\Nexus\Handler\MethodCanceller;
use Temporal\Nexus\LinkParser;
use Temporal\Nexus\NexusOperationContext;
use Temporal\Worker\Transport\Command\Client\NexusOperationStarted;
use Temporal\Worker\Transport\Command\ServerRequestInterface;

final class InvokeNexusOperation extends Route
{
    public function __construct(
        private readonly NexusTaskHandler $taskHandler,
        private readonly NexusInvocationRegistry $invocations,
        private readonly DataConverterInterface $dataConverter,
        private readonly MarshallerInterface $marshaller,
    ) {}

    public function handle(ServerRequestInterface $request, array $headers, Deferred $resolver): void
    {
        $options = $request->getOptions();
        $invocationId = (int) ($options['invocationId'] ?? 0);
        $operationContext = $this->marshaller->unmarshal($options, new NexusOperationContext());

        $canceller = null;
        if ($invocationId !== 0) {
            $canceller = new MethodCanceller(NexusTaskHandler::deadlineFromHeaders($options['headers'] ?? []));
            $this->invocations->register($invocationId, $canceller);
        }

        try {
            $protoRequest = self::buildProtoRequest($options, $request->getPayloads());
            $response = $this->taskHandler->handleStartOperation(
                $protoRequest,
                $canceller,
                $operationContext,
            );

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

            $resolver->reject(new \LogicException('NexusTaskHandler returned a response with no success variant'));
        } catch (NexusHandlerErrorException $e) {
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

    private function payloadAsValues(?Payload $payload): ValuesInterface
    {
        $payloads = new Payloads();
        if ($payload !== null) {
            $payloads->setPayloads([$payload]);
        }
        return EncodedValues::fromPayloads($payloads, $this->dataConverter);
    }
}
