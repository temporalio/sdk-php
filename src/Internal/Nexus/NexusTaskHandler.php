<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Nexus;

use Temporal\Nexus\Exception\HandlerException;
use Temporal\Nexus\Exception\InvalidArgumentException;
use Temporal\Nexus\Exception\OperationException;
use Temporal\Nexus\Exception\RetryBehavior;
use Temporal\Nexus\Handler\AsyncOperationStartResult;
use Temporal\Nexus\Handler\MethodCanceller;
use Temporal\Nexus\Handler\OperationCancelDetails;
use Temporal\Nexus\Handler\OperationContext;
use Temporal\Nexus\Handler\OperationStartDetails;
use Temporal\Nexus\Handler\Internal\ServiceHandler;
use Temporal\Nexus\Handler\SyncOperationStartResult;
use Temporal\Nexus\Header as NexusHeader;
use Temporal\Nexus\LinkParser;
use Temporal\Nexus\NexusOperationContext;
use Temporal\Api\Nexus\V1\CancelOperationRequest;
use Temporal\Api\Nexus\V1\CancelOperationResponse;
use Temporal\Api\Nexus\V1\Request;
use Temporal\Api\Nexus\V1\Response;
use Temporal\Api\Nexus\V1\StartOperationRequest;
use Temporal\Api\Nexus\V1\StartOperationResponse;
use Temporal\Client\WorkflowClientInterface;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Interceptor\PipelineProvider;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Internal\Declaration\Instantiator\NexusServiceInstantiator;
use Temporal\Internal\Declaration\Prototype\NexusServiceCollection;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Nexus\Exception\ErrorType;
use Temporal\Worker\Environment\EnvironmentInterface;

/**
 * Bridges Temporal RoadRunner tasks to the Nexus SDK ServiceHandler; ServiceHandler is built lazily.
 */
final class NexusTaskHandler
{
    private ?ServiceHandler $serviceHandler = null;

    public function __construct(
        private readonly NexusServiceCollection $repository,
        private readonly DataConverterInterface $dataConverter,
        private readonly EnvironmentInterface $env,
        private readonly PipelineProvider $interceptorProvider = new SimplePipelineProvider(),
        private readonly ?WorkflowClientInterface $workflowClient = null,
    ) {}

    /**
     * Absolute deadline from Operation-Timeout (preferred) or Request-Timeout; absent or unparseable yields null.
     *
     * @param array<string, string> $headers
     */
    public static function deadlineFromHeaders(array $headers): ?\DateTimeImmutable
    {
        $lowerHeaders = \array_change_key_case($headers, \CASE_LOWER);

        $value = NexusHeader::get($lowerHeaders, NexusHeader::OPERATION_TIMEOUT)
            ?? NexusHeader::get($lowerHeaders, NexusHeader::REQUEST_TIMEOUT);

        if ($value === null || $value === '') {
            return null;
        }

        try {
            return NexusHeader::deadlineFromTimeout($value);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    public function handleStartOperation(
        Request $request,
        NexusOperationContext $operationContext,
        ?MethodCanceller $methodCanceller = null,
    ): Response {
        $startRequest = $request->getStartOperation();
        \assert($startRequest instanceof StartOperationRequest);

        $headers = [];
        foreach ($request->getHeader() as $key => $value) {
            $headers[(string) $key] = (string) $value;
        }

        // Strict link parsing: malformed → BadRequest.
        $links = LinkParser::fromProto($startRequest->getLinks());

        $callbackHeaders = [];
        foreach ($startRequest->getCallbackHeader() as $key => $value) {
            $callbackHeaders[(string) $key] = (string) $value;
        }

        $context = new OperationContext(
            service: $startRequest->getService(),
            operation: $startRequest->getOperation(),
            headers: $headers,
            deadline: self::deadlineFromHeaders($headers),
            methodCanceller: $methodCanceller,
            env: $this->env,
        );

        $input = EncodedValues::fromPayload($startRequest->getPayload(), $this->dataConverter);

        try {
            $details = new OperationStartDetails(
                requestId: $startRequest->getRequestId(),
                callbackUrl: $startRequest->getCallback() ?: null,
                callbackHeaders: $callbackHeaders,
                links: $links,
            );

            $result = $this->getServiceHandler()->startOperation(
                $context,
                $details,
                $input,
                $this->workflowClient,
                $operationContext,
            );

            $startResponse = new StartOperationResponse();

            if ($result instanceof SyncOperationStartResult) {
                $syncResponse = new StartOperationResponse\Sync();

                if (!$result->value instanceof ValuesInterface) {
                    throw new \LogicException('sync start result must be ValuesInterface');
                }
                $resultPayload = EncodedValues::firstPayload($result->value);
                if ($resultPayload !== null) {
                    $syncResponse->setPayload($resultPayload);
                }

                $contextLinks = $context->links->all();
                if ($contextLinks !== []) {
                    $syncResponse->setLinks(NexusLinkConverter::toNexusProtoLinks($contextLinks));
                }

                $startResponse->setSyncSuccess($syncResponse);
            } else {
                \assert($result instanceof AsyncOperationStartResult);
                $asyncResponse = new StartOperationResponse\Async();
                /** @psalm-suppress DeprecatedMethod */
                $asyncResponse->setOperationId($result->info->token);
                $asyncResponse->setOperationToken($result->info->token);

                $contextLinks = $context->links->all();
                if ($contextLinks !== []) {
                    $asyncResponse->setLinks(NexusLinkConverter::toNexusProtoLinks($contextLinks));
                }

                $startResponse->setAsyncSuccess($asyncResponse);
            }

            $response = new Response();
            $response->setStartOperation($startResponse);
            return $response;
        } catch (OperationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw $this->toHandlerException($e);
        }
    }

    public function handleCancelOperation(
        Request $request,
        NexusOperationContext $operationContext,
    ): Response {
        $cancelRequest = $request->getCancelOperation();
        \assert($cancelRequest instanceof CancelOperationRequest);

        $headers = [];
        foreach ($request->getHeader() as $key => $value) {
            $headers[(string) $key] = (string) $value;
        }

        $context = new OperationContext(
            service: $cancelRequest->getService(),
            operation: $cancelRequest->getOperation(),
            headers: $headers,
            env: $this->env,
        );

        $token = $cancelRequest->getOperationToken();
        if ($token === '') {
            /** @psalm-suppress DeprecatedMethod — back-compat fallback */
            $token = $cancelRequest->getOperationId();
        }

        try {
            $details = new OperationCancelDetails(operationToken: $token);

            $this->getServiceHandler()->cancelOperation(
                $context,
                $details,
                $this->workflowClient,
                $operationContext,
            );

            $response = new Response();
            $response->setCancelOperation(new CancelOperationResponse());
            return $response;
        } catch (\Throwable $e) {
            throw $this->toHandlerException($e);
        }
    }

    private function getServiceHandler(): ServiceHandler
    {
        if ($this->serviceHandler === null) {
            $instantiator = new NexusServiceInstantiator();
            $instances = [];
            foreach ($this->repository as $prototype) {
                $instances[] = $instantiator->instantiate($prototype);
            }
            if ($instances === []) {
                throw new \RuntimeException('No Nexus service implementations registered');
            }

            $this->serviceHandler = ServiceHandler::create(
                dataConverter: $this->dataConverter,
                instances: $instances,
                interceptorProvider: $this->interceptorProvider,
            );
        }

        return $this->serviceHandler;
    }

    private function toHandlerException(\Throwable $e): HandlerException
    {
        if ($e instanceof HandlerException) {
            return $e;
        }

        if ($e instanceof InvalidArgumentException) {
            return HandlerException::fromCause(ErrorType::BadRequest, $e, RetryBehavior::NonRetryable);
        }

        return HandlerErrorMapper::mapToHandlerException($e) ?? HandlerException::fromCause(ErrorType::Internal, $e);
    }
}
