<?php

declare(strict_types=1);

namespace Temporal\Internal\Nexus;

use Nexus\Sdk\Exception\HandlerException;
use Nexus\Sdk\Exception\OperationException;
use Nexus\Sdk\Exception\RetryBehavior;
use Nexus\Sdk\Handler\HandlerInputContent;
use Nexus\Sdk\Handler\OperationCancelDetails;
use Nexus\Sdk\Handler\OperationContext;
use Nexus\Sdk\Handler\OperationStartDetails;
use Nexus\Sdk\Handler\ServiceHandler;
use Nexus\Sdk\Link;
use Nexus\Sdk\Serializer\SerializerInterface;
use Temporal\Api\Enums\V1\NexusHandlerErrorRetryBehavior;
use Temporal\Api\Nexus\V1\CancelOperationRequest;
use Temporal\Api\Nexus\V1\CancelOperationResponse;
use Temporal\Api\Nexus\V1\Failure;
use Temporal\Api\Nexus\V1\HandlerError;
use Temporal\Api\Nexus\V1\Request;
use Temporal\Api\Nexus\V1\Response;
use Temporal\Api\Nexus\V1\StartOperationRequest;
use Temporal\Api\Nexus\V1\StartOperationResponse;
use Temporal\Api\Nexus\V1\UnsuccessfulOperationError;

/**
 * Bridges Temporal's RoadRunner tasks to the Nexus SDK ServiceHandler.
 *
 * The ServiceHandler is built lazily on first use so that Nexus service
 * implementations can be registered after the worker is constructed.
 */
final class NexusTaskHandler
{
    private ?ServiceHandler $serviceHandler = null;

    public function __construct(
        private readonly NexusServiceRepository $repository,
        private readonly SerializerInterface $serializer,
        private readonly ?\Temporal\DataConverter\DataConverterInterface $dataConverter = null,
    ) {}

    public function handleStartOperation(Request $request): Response
    {
        $startReq = $request->getStartOperation();
        \assert($startReq instanceof StartOperationRequest);

        $headers = [];
        foreach ($request->getHeader() as $key => $value) {
            $headers[(string) $key] = (string) $value;
        }

        $links = [];
        foreach ($startReq->getLinks() as $protoLink) {
            $links[] = new Link($protoLink->getUrl(), $protoLink->getType());
        }

        $callbackHeaders = [];
        foreach ($startReq->getCallbackHeader() as $key => $value) {
            $callbackHeaders[(string) $key] = (string) $value;
        }

        $context = OperationContext::create(
            service: $startReq->getService(),
            operation: $startReq->getOperation(),
            headers: $headers,
        );

        $details = new OperationStartDetails(
            requestId: $startReq->getRequestId(),
            callbackUrl: $startReq->getCallback() !== '' ? $startReq->getCallback() : null,
            callbackHeaders: $callbackHeaders,
            links: $links,
        );

        $inputData = '';
        $inputHeaders = [];
        $payload = $startReq->getPayload();
        if ($payload !== null) {
            $inputData = $payload->getData();
            foreach ($payload->getMetadata() as $key => $value) {
                $inputHeaders[(string) $key] = (string) $value;
            }
        }
        $input = new HandlerInputContent($inputData, $inputHeaders);

        try {
            $result = $this->getServiceHandler()->startOperation($context, $details, $input);

            $startResp = new StartOperationResponse();

            if ($result->isSync()) {
                $syncResult = $result->getSyncResult();
                $syncResp = new StartOperationResponse\Sync();

                if ($syncResult !== null) {
                    $resultPayload = new \Temporal\Api\Common\V1\Payload();
                    $resultPayload->setData($syncResult->data);
                    if ($syncResult->headers !== []) {
                        $resultPayload->setMetadata($syncResult->headers);
                    }
                    $syncResp->setPayload($resultPayload);
                }

                $contextLinks = $context->getLinks();
                if ($contextLinks !== []) {
                    $syncResp->setLinks($this->convertLinksToProto($contextLinks));
                }

                $startResp->setSyncSuccess($syncResp);
            } else {
                $asyncResp = new StartOperationResponse\Async();
                $asyncResp->setOperationToken($result->getAsyncOperationToken());

                $contextLinks = $context->getLinks();
                if ($contextLinks !== []) {
                    $asyncResp->setLinks($this->convertLinksToProto($contextLinks));
                }

                $startResp->setAsyncSuccess($asyncResp);
            }

            $response = new Response();
            $response->setStartOperation($startResp);
            return $response;
        } catch (OperationException $e) {
            return $this->buildOperationErrorResponse($e);
        } catch (HandlerException $e) {
            throw $this->convertHandlerException($e);
        }
    }

    public function handleCancelOperation(Request $request): Response
    {
        $cancelReq = $request->getCancelOperation();
        \assert($cancelReq instanceof CancelOperationRequest);

        $headers = [];
        foreach ($request->getHeader() as $key => $value) {
            $headers[(string) $key] = (string) $value;
        }

        $context = OperationContext::create(
            service: $cancelReq->getService(),
            operation: $cancelReq->getOperation(),
            headers: $headers,
        );

        $token = $cancelReq->getOperationToken();
        if ($token === '') {
            $token = $cancelReq->getOperationId();
        }

        $details = new OperationCancelDetails(operationToken: $token);

        try {
            $this->getServiceHandler()->cancelOperation($context, $details);

            $response = new Response();
            $response->setCancelOperation(new CancelOperationResponse());
            return $response;
        } catch (HandlerException $e) {
            throw $this->convertHandlerException($e);
        }
    }

    /**
     * Start operation directly from parsed context/details/input (used by InvokeNexusOperation route).
     * Returns EncodedValues with the raw serialized payload for the Go codec.
     */
    public function startOperationDirect(
        OperationContext $context,
        OperationStartDetails $details,
        HandlerInputContent $input,
    ): \Temporal\DataConverter\EncodedValues {
        try {
            $result = $this->getServiceHandler()->startOperation($context, $details, $input);

            if ($result->isSync()) {
                $syncResult = $result->getSyncResult();
                if ($syncResult !== null) {
                    // Build a raw Payload with already-serialized data + metadata
                    $payload = new \Temporal\Api\Common\V1\Payload();
                    $payload->setData($syncResult->data);
                    if ($syncResult->headers !== []) {
                        $payload->setMetadata($syncResult->headers);
                    }
                    $payloads = new \Temporal\Api\Common\V1\Payloads(['payloads' => [$payload]]);
                    return \Temporal\DataConverter\EncodedValues::fromPayloads($payloads, $this->dataConverter);
                }
                return \Temporal\DataConverter\EncodedValues::fromValues([null]);
            }

            // Async: return token as the result
            return \Temporal\DataConverter\EncodedValues::fromValues([$result->getAsyncOperationToken()]);
        } catch (OperationException $e) {
            throw $this->convertHandlerException(
                new HandlerException(
                    \Nexus\Sdk\Exception\ErrorType::Internal,
                    $e->getMessage(),
                    $e,
                ),
            );
        } catch (HandlerException $e) {
            throw $this->convertHandlerException($e);
        }
    }

    /**
     * Cancel operation directly from parsed context/details (used by CancelNexusOperation route).
     */
    public function cancelOperationDirect(
        OperationContext $context,
        OperationCancelDetails $details,
    ): void {
        try {
            $this->getServiceHandler()->cancelOperation($context, $details);
        } catch (HandlerException $e) {
            throw $this->convertHandlerException($e);
        }
    }

    private function getServiceHandler(): ServiceHandler
    {
        if ($this->serviceHandler === null) {
            $instances = $this->repository->getInstances();
            if ($instances === []) {
                throw new \RuntimeException('No Nexus service implementations registered');
            }

            $this->serviceHandler = ServiceHandler::create(
                serializer: $this->serializer,
                instances: $instances,
            );
        }

        return $this->serviceHandler;
    }

    private function buildOperationErrorResponse(OperationException $e): Response
    {
        $failure = new Failure();
        $failure->setMessage($e->getMessage());

        $opError = new UnsuccessfulOperationError();
        $opError->setOperationState(\strtolower($e->state->value));
        $opError->setFailure($failure);

        $startResp = new StartOperationResponse();
        $startResp->setOperationError($opError);

        $response = new Response();
        $response->setStartOperation($startResp);
        return $response;
    }

    private function convertHandlerException(HandlerException $e): NexusHandlerErrorException
    {
        $handlerError = new HandlerError();
        $handlerError->setErrorType($e->rawErrorType);

        $retryBehavior = match ($e->retryBehavior) {
            RetryBehavior::Retryable => NexusHandlerErrorRetryBehavior::NEXUS_HANDLER_ERROR_RETRY_BEHAVIOR_RETRYABLE,
            RetryBehavior::NonRetryable => NexusHandlerErrorRetryBehavior::NEXUS_HANDLER_ERROR_RETRY_BEHAVIOR_NON_RETRYABLE,
            default => NexusHandlerErrorRetryBehavior::NEXUS_HANDLER_ERROR_RETRY_BEHAVIOR_UNSPECIFIED,
        };
        $handlerError->setRetryBehavior($retryBehavior);

        $failure = new Failure();
        $failure->setMessage($e->getMessage());
        $handlerError->setFailure($failure);

        return new NexusHandlerErrorException($handlerError, $e);
    }

    /**
     * @param Link[] $links
     * @return \Temporal\Api\Nexus\V1\Link[]
     */
    private function convertLinksToProto(array $links): array
    {
        $protoLinks = [];
        foreach ($links as $link) {
            $protoLink = new \Temporal\Api\Nexus\V1\Link();
            $protoLink->setUrl($link->uri);
            $protoLink->setType($link->type);
            $protoLinks[] = $protoLink;
        }
        return $protoLinks;
    }
}
