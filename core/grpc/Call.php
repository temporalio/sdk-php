<?php

declare(strict_types=1);

namespace Grpc;

class Call
{
    private const CLOSED_ERROR_CODE = 1;
    private const BINARY_HEADER_SUFFIX = '-bin';
    private const STATUS_DETAILS_HEADER = 'grpc-status-details-bin';
    private const CANCELLED_DETAILS = 'CANCELLED';
    private const INSECURE_CREDENTIALS_DETAILS = 'Established channel does not have a sufficient security level to transfer call credential.';
    private const SECURE_SCHEME = 'https://';
    private const DEFAULT_PORT_SUFFIX = ':443';
    private const LENGTH_PREFIX_BYTES = 4;

    /** @var array<string, list<string>> */
    private array $metadata = [];

    private string $message = '';
    private ?CallCredentials $credentials = null;
    private ?int $tag = null;

    /** @var array{?string, \stdClass}|null */
    private ?array $response = null;

    private bool $received = false;

    public function __construct(
        private readonly Channel $channel,
        private readonly string $method,
        private readonly Timeval $deadline,
    ) {
        if ($channel->isClosed()) {
            throw new \InvalidArgumentException('Call cannot be constructed from a closed Channel', self::CLOSED_ERROR_CODE);
        }
    }

    public function startBatch(array $ops): \stdClass
    {
        if ($this->channel->isClosed()) {
            throw new \RuntimeException('startBatch Error. Channel is closed', self::CLOSED_ERROR_CODE);
        }
        $event = new \stdClass();
        if (\array_key_exists(OP_SEND_INITIAL_METADATA, $ops)) {
            /** @var array<string, list<string>> $metadata */
            $metadata = $ops[OP_SEND_INITIAL_METADATA];
            $this->metadata = $metadata;
            $event->send_metadata = true;
        }
        if (\array_key_exists(OP_SEND_MESSAGE, $ops)) {
            /** @var array{message: string} $message */
            $message = $ops[OP_SEND_MESSAGE];
            $this->message = $message['message'];
            $event->send_message = true;
        }
        if (\array_key_exists(OP_SEND_CLOSE_FROM_CLIENT, $ops)) {
            $this->start();
            $event->send_close = true;
        }
        if (\array_key_exists(OP_RECV_INITIAL_METADATA, $ops)) {
            $event->metadata = [];
        }
        $receiveMessage = \array_key_exists(OP_RECV_MESSAGE, $ops);
        $receiveStatus = \array_key_exists(OP_RECV_STATUS_ON_CLIENT, $ops);
        if ($receiveMessage || $receiveStatus) {
            [$message, $status] = $this->receive();
            if ($receiveMessage) {
                $event->message = $message;
            }
            if ($receiveStatus) {
                $event->status = $status;
            }
        }

        return $event;
    }

    public function getPeer(): string
    {
        return $this->channel->getTarget();
    }

    public function cancel(): void
    {
        if ($this->tag !== null) {
            $this->channel->forgetCall($this->tag);
            $this->tag = null;
            $this->response = [null, self::status(STATUS_CANCELLED, self::CANCELLED_DETAILS, [])];
        }
    }

    public function setCredentials(CallCredentials $credentials): int
    {
        $this->credentials = $credentials;

        return CALL_OK;
    }

    public function __destruct()
    {
        $this->cancel();
    }

    /**
     * @param array<string, list<string>> $metadata
     */
    private static function status(int $code, string $details, array $metadata): \stdClass
    {
        $status = new \stdClass();
        $status->metadata = $metadata;
        $status->code = $code;
        $status->details = $details;

        return $status;
    }

    private function start(): void
    {
        if ($this->credentials !== null && !$this->channel->isSecure()) {
            $this->response = [null, self::status(STATUS_UNAUTHENTICATED, self::INSECURE_CREDENTIALS_DETAILS, [])];

            return;
        }
        $metadata = $this->metadata;
        if ($this->credentials !== null) {
            $metadata = \array_merge_recursive($metadata, $this->credentialsMetadata($this->credentials));
        }
        foreach ($metadata as $key => $values) {
            if (\str_ends_with($key, self::BINARY_HEADER_SUFFIX)) {
                $metadata[$key] = \array_map(\base64_encode(...), $values);
            }
        }
        $timeoutMs = Timeval::compare($this->deadline, Timeval::infFuture()) === 0 ? 0 : \max(1, $this->deadline->millisecondsLeft());
        $this->tag = $this->channel->startCall($this->method, $this->message, $metadata, $timeoutMs);
    }

    /**
     * @return array<string, list<string>>
     */
    private function credentialsMetadata(CallCredentials $credentials): array
    {
        $slash = (int) \strrpos($this->method, '/');
        $authority = $this->channel->authority();
        if (\str_ends_with($authority, self::DEFAULT_PORT_SUFFIX)) {
            $authority = \substr($authority, 0, -\strlen(self::DEFAULT_PORT_SUFFIX));
        }

        return $credentials->metadata(
            self::SECURE_SCHEME . $authority . \substr($this->method, 0, $slash),
            \substr($this->method, $slash + 1),
        );
    }

    /**
     * @return array{?string, \stdClass}
     */
    private function receive(): array
    {
        if ($this->received || ($this->tag === null && $this->response === null)) {
            throw new \LogicException('start_batch was called incorrectly', CALL_ERROR_TOO_MANY_OPERATIONS);
        }
        $this->received = true;
        if ($this->response !== null) {
            return $this->response;
        }
        $tag = $this->tag;
        $this->tag = null;
        [$code, $data] = $this->channel->finishCall($tag);
        if ($code === STATUS_OK) {
            return [$data, self::status($code, '', [])];
        }
        /** @var array{1: int} $header */
        $header = \unpack('V', $data);
        $length = $header[1];
        $details = \substr($data, self::LENGTH_PREFIX_BYTES + $length);

        return [null, self::status($code, \substr($data, self::LENGTH_PREFIX_BYTES, $length), $details === '' ? [] : [self::STATUS_DETAILS_HEADER => [$details]])];
    }
}
