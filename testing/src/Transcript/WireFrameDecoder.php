<?php

declare(strict_types=1);

namespace Temporal\Testing\Transcript;

use Google\Protobuf\Internal\MapField;
use RoadRunner\Temporal\DTO\V1\Frame;
use RoadRunner\Temporal\DTO\V1\Message;
use Temporal\Api\Common\V1\Header;
use Temporal\Api\Common\V1\Payload;
use Temporal\Api\Common\V1\Payloads;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodingKeys;

final class WireFrameDecoder
{
    private static ?DataConverterInterface $defaultConverter = null;

    /**
     * Attempts to decode a wire frame as `temporal.v1.Frame` (RoadRunner Temporal protocol).
     *
     * @return array{encoding: string, messages: list<array<string, mixed>>}|null
     */
    public static function decode(string $frame, ?DataConverterInterface $converter = null): ?array
    {
        if ($frame === '') {
            return null;
        }

        $proto = new Frame();
        try {
            $proto->mergeFromString($frame);
        } catch (\Throwable) {
            return null;
        }

        $messages = $proto->getMessages();
        if (\count($messages) === 0) {
            return null;
        }

        $converter ??= self::$defaultConverter ??= DataConverter::createDefault();

        $decoded = [];
        foreach ($messages as $message) {
            $decoded[] = self::decodeMessage($message, $converter);
        }

        return [
            'encoding' => 'temporal-frame',
            'messages' => $decoded,
        ];
    }

    /**
     * Uses proto's native JSON serialization to extract non-default fields, then
     * replaces bytes-shaped fields (`options`, `payloads`, `header`) with their
     * SDK-decoded, human-readable representation.
     *
     * @return array<string, mixed>
     */
    private static function decodeMessage(Message $message, DataConverterInterface $converter): array
    {
        try {
            $json = $message->serializeToJsonString(true);
        } catch (\Throwable) {
            return ['error' => 'proto_json_serialize_failed'];
        }
        $decoded = \json_decode($json, true) ?? [];

        if ($message->getOptions() !== '') {
            $decoded['options'] = self::decodeJsonBytes($message->getOptions());
        }
        if ($message->hasPayloads()) {
            $decoded['payloads'] = self::decodePayloads($message->getPayloads(), $converter);
        }
        if ($message->hasHeader()) {
            $decoded['header'] = self::decodeHeader($message->getHeader(), $converter);
        }

        return $decoded;
    }

    /**
     * @return list<mixed>
     */
    private static function decodePayloads(Payloads $payloads, DataConverterInterface $converter): array
    {
        $out = [];
        foreach ($payloads->getPayloads() as $payload) {
            $out[] = self::decodeSinglePayload($payload, $converter);
        }
        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private static function decodeHeader(Header $header, DataConverterInterface $converter): array
    {
        $out = [];
        /** @var MapField<string, Payload> $fields */
        $fields = $header->getFields();
        foreach ($fields as $name => $payload) {
            $out[$name] = self::decodeSinglePayload($payload, $converter);
        }
        return $out;
    }

    /**
     * Decodes a single payload using its own `metadata.encoding`. Falls back to a raw
     * representation when encoding is absent (e.g., {@see \Temporal\DataConverter\RawValue})
     * or when the converter cannot interpret the bytes.
     */
    private static function decodeSinglePayload(Payload $payload, DataConverterInterface $converter): mixed
    {
        /** @var MapField<string, string> $meta */
        $meta = $payload->getMetadata();
        if (!isset($meta[EncodingKeys::METADATA_ENCODING_KEY])) {
            return self::payloadFallback($payload);
        }
        try {
            return $converter->fromPayload($payload, null);
        } catch (\Throwable) {
            return self::payloadFallback($payload);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function payloadFallback(Payload $payload): array
    {
        $metadata = [];
        $meta = $payload->getMetadata();
        foreach ($meta as $key => $value) {
            $metadata[$key] = self::bytesToReadable((string) $value);
        }
        return [
            'metadata' => $metadata,
            'data' => self::bytesToReadable($payload->getData()),
        ];
    }

    private static function decodeJsonBytes(string $bytes): mixed
    {
        $decoded = \json_decode($bytes, true);
        if ($decoded !== null || \json_last_error() === \JSON_ERROR_NONE) {
            return $decoded;
        }
        return self::bytesToReadable($bytes);
    }

    /**
     * Returns the bytes as a UTF-8 string when valid, otherwise wraps the
     * bytes in a base64 representation so the JSON line stays well-formed.
     */
    private static function bytesToReadable(string $bytes): mixed
    {
        if ($bytes === '') {
            return '';
        }
        if (\preg_match('//u', $bytes) === 1) {
            return $bytes;
        }
        return ['encoding' => 'base64', 'value' => \base64_encode($bytes)];
    }
}
