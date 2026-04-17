<?php

declare(strict_types=1);

namespace Temporal\Internal\Nexus;

use Nexus\Sdk\Exception\HandlerException;
use Nexus\Sdk\Exception\OperationException;
use Nexus\Sdk\Exception\RetryBehavior;
use Nexus\Sdk\Handler\AsyncOperationStartResult;
use Nexus\Sdk\Handler\HandlerInputContent;
use Nexus\Sdk\Handler\HandlerResultContent;
use Nexus\Sdk\Handler\OperationCancelDetails;
use Nexus\Sdk\Handler\OperationContext;
use Nexus\Sdk\Handler\OperationStartDetails;
use Nexus\Sdk\Handler\ServiceHandler;
use Nexus\Sdk\Handler\SyncOperationStartResult;
use Nexus\Sdk\Header as NexusHeader;
use Nexus\Sdk\Link;
use Nexus\Sdk\Serializer\SerializerInterface;
use Temporal\Api\Common\V1\Payload;
use Temporal\Api\Common\V1\Payloads;
use Temporal\Api\Enums\V1\NexusHandlerErrorRetryBehavior;
use Temporal\Api\Nexus\V1\CancelOperationRequest;
use Temporal\Api\Nexus\V1\CancelOperationResponse;
use Temporal\Api\Nexus\V1\Failure;
use Temporal\Api\Nexus\V1\HandlerError;
use Temporal\Api\Nexus\V1\Link as ProtoLink;
use Temporal\Api\Nexus\V1\Request;
use Temporal\Api\Nexus\V1\Response;
use Temporal\Api\Nexus\V1\StartOperationRequest;
use Temporal\Api\Nexus\V1\StartOperationResponse;
use Temporal\Api\Nexus\V1\UnsuccessfulOperationError;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedValues;

/**
 * Bridges Temporal's RoadRunner tasks to the Nexus SDK ServiceHandler.
 *
 * The ServiceHandler is built lazily on first use so that Nexus service
 * implementations can be registered after the worker is constructed.
 */
final class NexusTaskHandler
{
    /**
     * Payload metadata marker identifying how the returned payload should be
     * interpreted by the RR Go side:
     *  - value "async" — payload.data is the async operation token (string)
     *  - absent        — payload is a regular sync result
     *
     * @internal Wire contract with roadrunner-temporal's aggregatedpool/nexus.go.
     */
    public const NEXUS_KIND_METADATA_KEY = '_rr_nexus_kind';

    public const NEXUS_KIND_ASYNC = 'async';

    /**
     * Payload metadata marker carrying handler-side links (from
     * `OperationContext::$links->add()`) as a JSON-encoded array of
     * `[{"url": ..., "type": ...}, ...]`. RoadRunner's Nexus handler parses
     * this on decode, attaches the links to the nexus result
     * (sync or async), and strips the key from the payload metadata so it
     * never leaks to the caller.
     *
     * @internal Wire contract with roadrunner-temporal's aggregatedpool/nexus.go.
     */
    public const NEXUS_LINKS_METADATA_KEY = '_rr_nexus_links';

    /**
     * Controls whether handler-side PHP traces (class, message, stack) are
     * attached to the Nexus `Failure.details` JSON blob.
     *
     * ⚠ Traces may contain absolute filesystem paths and internal argument
     * values — sensitive in cross-tenant / cross-cluster deployments where
     * the Nexus caller is outside the trust boundary. Set to `false` in
     * production to emit only the message.
     *
     * Default is `true` to preserve existing debuggability.
     */
    public static bool $includeTracebackInFailure = true;

    private ?ServiceHandler $serviceHandler = null;

    public function __construct(
        private readonly NexusServiceRepository $repository,
        private readonly SerializerInterface $serializer,
        private readonly DataConverterInterface $dataConverter,
    ) {}

    /**
     * Build an absolute deadline from Nexus timeout headers.
     *
     * `Operation-Timeout` wins over `Request-Timeout` — the former is the outer
     * budget including callbacks. Case-insensitive lookup. Malformed values are
     * silently ignored so a bad header never drops an otherwise-valid request.
     *
     * @param array<string, string> $headers
     */
    public static function deadlineFromHeaders(array $headers): ?\DateTimeImmutable
    {
        // Headers off the wire are not guaranteed lowercase; normalize once so
        // NexusHeader::get() (which assumes a lowercased map) behaves correctly.
        $lc = \array_change_key_case($headers, \CASE_LOWER);

        $value = NexusHeader::get($lc, NexusHeader::OPERATION_TIMEOUT)
            ?? NexusHeader::get($lc, NexusHeader::REQUEST_TIMEOUT);

        if ($value === null || $value === '') {
            return null;
        }

        try {
            return NexusHeader::deadlineFromTimeout($value);
        } catch (\Nexus\Sdk\Exception\InvalidArgumentException) {
            return null;
        }
    }

    public function handleStartOperation(Request $request): Response
    {
        $startReq = $request->getStartOperation();
        \assert($startReq instanceof StartOperationRequest);

        $headers = [];
        foreach ($request->getHeader() as $key => $value) {
            $headers[(string) $key] = (string) $value;
        }

        // Strict parsing — any malformed link raises HandlerException(BadRequest),
        // matching the Java reference and the RR route path.
        $links = LinkParser::fromProto($startReq->getLinks());

        $callbackHeaders = [];
        foreach ($startReq->getCallbackHeader() as $key => $value) {
            $callbackHeaders[(string) $key] = (string) $value;
        }

        $context = new OperationContext(
            service: $startReq->getService(),
            operation: $startReq->getOperation(),
            headers: $headers,
            deadline: self::deadlineFromHeaders($headers),
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

            if ($result instanceof SyncOperationStartResult) {
                $syncResp = new StartOperationResponse\Sync();

                // ServiceHandler wraps the raw return value in HandlerResultContent
                // (data + headers) before handing it to us — no extra serialization.
                $content = $result->value;
                if ($content instanceof HandlerResultContent) {
                    $resultPayload = new Payload();
                    $resultPayload->setData($content->data);
                    if ($content->headers !== []) {
                        $resultPayload->setMetadata($content->headers);
                    }
                    $syncResp->setPayload($resultPayload);
                }

                $contextLinks = $context->links->all();
                if ($contextLinks !== []) {
                    $syncResp->setLinks($this->convertLinksToProto($contextLinks));
                }

                $startResp->setSyncSuccess($syncResp);
            } else {
                \assert($result instanceof AsyncOperationStartResult);
                // AsyncOperationStartResult's constructor validates the token
                // is non-empty printable ASCII, so no extra guard needed here.
                $asyncResp = new StartOperationResponse\Async();
                $asyncResp->setOperationToken($result->operationToken);

                $contextLinks = $context->links->all();
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

        $context = new OperationContext(
            service: $cancelReq->getService(),
            operation: $cancelReq->getOperation(),
            headers: $headers,
        );

        $token = $cancelReq->getOperationToken();
        if ($token === '') {
            /** @psalm-suppress DeprecatedMethod — intentional back-compat fallback */
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
     *
     * Returns the start result as a raw EncodedValues payload for the Go codec.
     * Errors are propagated as the native Nexus SDK exceptions:
     *  - HandlerException → FailureConverter sets NexusHandlerFailureInfo on the
     *    Failure proto, RR picks up errorType + retryBehavior.
     *  - OperationException → propagated as-is; FailureConverter preserves its
     *    state (failed/canceled) so RR can emit a nexus.OperationError rather
     *    than collapsing into nexus.HandlerError{Internal}.
     *
     * @throws HandlerException
     * @throws OperationException
     */
    public function startOperationDirect(
        OperationContext $context,
        OperationStartDetails $details,
        HandlerInputContent $input,
    ): EncodedValues {
        $result = $this->getServiceHandler()->startOperation($context, $details, $input);

        // Handler-side links attached via $ctx->links->add() need to reach the
        // Nexus caller. Since RR's InvokeNexusOperation route returns raw
        // EncodedValues (not the StartOperationResponse proto), we piggyback
        // them on the payload metadata with a reserved `_rr_nexus_*` key.
        $linksJson = self::encodeLinksMetadata($context->links->all());

        if ($result instanceof SyncOperationStartResult) {
            $payload = new Payload();
            $metadata = [];
            // ServiceHandler already serialized the return value into
            // HandlerResultContent — we just forward its bytes + headers.
            $content = $result->value;
            if ($content instanceof HandlerResultContent) {
                $payload->setData($content->data);
                $metadata = $content->headers;
            }
            if ($linksJson !== null) {
                $metadata[self::NEXUS_LINKS_METADATA_KEY] = $linksJson;
            }
            if ($metadata !== []) {
                $payload->setMetadata($metadata);
            }

            $payloads = new Payloads(['payloads' => [$payload]]);
            return EncodedValues::fromPayloads($payloads, $this->dataConverter);
        }

        \assert($result instanceof AsyncOperationStartResult);
        // Async: return an opaque payload carrying the operation token plus a
        // well-known metadata marker so RR can distinguish it from a sync result.
        // AsyncOperationStartResult validates token shape at construction
        // (printable ASCII, non-empty), safe to pass as payload bytes.
        $payload = new Payload();
        $payload->setData($result->operationToken);
        $metadata = [
            self::NEXUS_KIND_METADATA_KEY => self::NEXUS_KIND_ASYNC,
        ];
        if ($linksJson !== null) {
            $metadata[self::NEXUS_LINKS_METADATA_KEY] = $linksJson;
        }
        $payload->setMetadata($metadata);
        $payloads = new Payloads(['payloads' => [$payload]]);
        return EncodedValues::fromPayloads($payloads, $this->dataConverter);
    }

    /**
     * Cancel operation directly from parsed context/details (used by CancelNexusOperation route).
     *
     * @throws HandlerException
     */
    public function cancelOperationDirect(
        OperationContext $context,
        OperationCancelDetails $details,
    ): void {
        $this->getServiceHandler()->cancelOperation($context, $details);
    }

    /**
     * Serialize handler-side links for the `_rr_nexus_links` metadata channel.
     *
     * Returns `null` when the list is empty so we don't bloat payloads with
     * an unnecessary `[]`. Encoded as a JSON array of `{url, type}` objects
     * matching RoadRunner's `internal.NexusLink` struct.
     *
     * @param Link[] $links
     */
    private static function encodeLinksMetadata(array $links): ?string
    {
        if ($links === []) {
            return null;
        }
        $out = [];
        foreach ($links as $link) {
            $out[] = ['url' => $link->uri, 'type' => $link->type];
        }
        // JSON_UNESCAPED_SLASHES keeps URLs readable; JSON_THROW_ON_ERROR
        // surfaces pathological inputs as a handler error rather than
        // silent data loss.
        return \json_encode($out, \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR);
    }

    /**
     * Pack a PHP exception's type, stack trace, and cause chain into a
     * Nexus-spec {@see Failure}'s `details` field as JSON, and stamp the
     * outermost exception class in `metadata["type"]`.
     *
     * The Nexus Failure envelope intentionally exposes only
     * `{message, metadata, details}` — to avoid losing debugging
     * information we serialise the trace into `details`. Integrators that
     * don't care can ignore the extra bytes; those that do can
     * `json_decode()` them back into a flat array of
     * `[{type, message, trace}, ...]` entries (outermost first).
     */
    private static function attachTracebackAsDetails(Failure $failure, \Throwable $e): void
    {
        $failure->setMetadata(['type' => $e::class]);

        if (!self::$includeTracebackInFailure) {
            // Stripped mode: keep the outer class name as type metadata but
            // emit no details. The caller still receives the message via
            // Failure.message, which is the minimum useful diagnostic.
            return;
        }

        $chain = [];
        $cursor = $e;
        // Bounded walk guards against a pathological cyclic cause chain.
        for ($depth = 0; $cursor !== null && $depth < 16; $depth++) {
            $chain[] = [
                'type' => $cursor::class,
                'message' => $cursor->getMessage(),
                'trace' => $cursor->getTraceAsString(),
            ];
            $cursor = $cursor->getPrevious();
        }

        $failure->setDetails(\json_encode($chain, \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR));
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
        // Nexus-spec Failure is a flat {message, metadata, details} envelope
        // (not Temporal's Failure with its stack-trace/cause fields). Pack
        // the PHP stack trace and cause chain into `details` as JSON so a
        // Nexus caller can still recover them.
        $failure = new Failure();
        $failure->setMessage($e->getMessage());
        self::attachTracebackAsDetails($failure, $e);

        $opError = new UnsuccessfulOperationError();
        // OperationState values are spec-mandated lowercase strings (running|succeeded|failed|canceled).
        $opError->setOperationState($e->state->value);
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
        self::attachTracebackAsDetails($failure, $e);
        $handlerError->setFailure($failure);

        return new NexusHandlerErrorException($handlerError, $e);
    }

    /**
     * @param Link[] $links
     * @return ProtoLink[]
     */
    private function convertLinksToProto(array $links): array
    {
        $protoLinks = [];
        foreach ($links as $link) {
            $protoLink = new ProtoLink();
            $protoLink->setUrl($link->uri);
            $protoLink->setType($link->type);
            $protoLinks[] = $protoLink;
        }
        return $protoLinks;
    }
}
