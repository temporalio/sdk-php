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
use Temporal\Api\Nexus\V1\Request;
use Temporal\Api\Nexus\V1\StartOperationRequest;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\Common\Uuid;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Nexus\NexusHandlerErrorException;
use Temporal\Internal\Nexus\NexusInvocationRegistry;
use Temporal\Internal\Nexus\NexusLinkConverter;
use Temporal\Internal\Nexus\NexusTaskHandler;
use Temporal\Nexus\Handler\MethodCanceller;
use Temporal\Nexus\LinkParser;
use Temporal\Nexus\NexusOperationContext;
use Temporal\Worker\Environment\EnvironmentInterface;
use Temporal\Worker\Transport\Command\Client\CommandResponse;
use Temporal\Worker\Transport\Command\ServerRequestInterface;

final class InvokeNexusOperation extends Route
{
    private const COMMAND = 'NexusOperationStarted';

    public function __construct(
        private readonly NexusTaskHandler $taskHandler,
        private readonly NexusInvocationRegistry $invocations,
        private readonly DataConverterInterface $dataConverter,
        private readonly MarshallerInterface $marshaller,
        private readonly EnvironmentInterface $env,
    ) {}

    public function handle(ServerRequestInterface $request, array $headers, Deferred $resolver): void
    {
        $options = $request->getOptions();
        $invocationId = (int) ($options['invocationId'] ?? 0);
        $operationContext = $this->marshaller->unmarshal($options, new NexusOperationContext());

        $canceller = null;
        if ($invocationId !== 0) {
            $canceller = new MethodCanceller(
                $this->env,
                NexusTaskHandler::deadlineFromHeaders($options['headers'] ?? []),
            );
            $this->invocations->register($invocationId, $canceller);
        }

        try {
            $protoRequest = self::buildProtoRequest($options, $request->getPayloads());
            $response = $this->taskHandler->handleStartOperation(
                $protoRequest,
                $operationContext,
                $canceller,
            );

            $startResponse = $response->getStartOperation();
            \assert($startResponse !== null);

            if ($startResponse->hasSyncSuccess()) {
                $sync = $startResponse->getSyncSuccess();
                \assert($sync !== null);
                $options = ['async' => false];
                $protoLinks = $sync->getLinks();
                $payloads = EncodedValues::fromPayload($sync->getPayload(), $this->dataConverter);
            } elseif ($startResponse->hasAsyncSuccess()) {
                $async = $startResponse->getAsyncSuccess();
                \assert($async !== null);
                $options = ['async' => true];
                $token = $async->getOperationToken();
                if ($token !== '') {
                    $options['token'] = $token;
                }
                $protoLinks = $async->getLinks();
                $payloads = null;
            } else {
                $resolver->reject(new \LogicException('NexusTaskHandler returned a response with no success variant'));
                return;
            }

            $links = self::linksToWire($protoLinks);
            if ($links !== []) {
                $options['links'] = $links;
            }

            $resolver->resolve(new CommandResponse(
                command: self::COMMAND,
                options: $options,
                payloads: $payloads,
            ));
        } catch (NexusHandlerErrorException $e) {
            $resolver->reject($e->cause);
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
        $requestId = ((string) ($options['requestId'] ?? '')) ?: Uuid::v4();

        $startRequest = (new StartOperationRequest())
            ->setService((string) ($options['service'] ?? ''))
            ->setOperation((string) ($options['operation'] ?? ''))
            ->setRequestId($requestId)
            ->setCallback((string) ($options['callback'] ?? ''))
            ->setCallbackHeader((array) ($options['callbackHeaders'] ?? []))
            ->setLinks(NexusLinkConverter::toNexusProtoLinks(
                LinkParser::fromRaw($options['links'] ?? null),
            ));

        $inputPayload = EncodedValues::firstPayload($payloads);
        if ($inputPayload !== null) {
            $startRequest->setPayload($inputPayload);
        }

        return (new Request())
            ->setHeader((array) ($options['headers'] ?? []))
            ->setStartOperation($startRequest);
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
}
