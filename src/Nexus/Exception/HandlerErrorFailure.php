<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus\Exception;

use Temporal\Nexus\FailureInfo;

/**
 * Canonical mapping between {@see HandlerException} and the Nexus-spec
 * `HandlerError` failure shape. Use {@see self::from()} on the producing side
 * and `read*` helpers on the consuming side.
 *
 * @see https://github.com/nexus-rpc/api/blob/main/SPEC.md
 */
final class HandlerErrorFailure
{
    /** Value of `metadata.type` that marks a HandlerError failure. */
    public const TYPE = 'nexus.HandlerError';

    /** Key in the `metadata` map that carries the failure-shape discriminator. */
    public const METADATA_TYPE_KEY = 'type';

    /** Key inside `details` that carries the predefined {@see ErrorType}. */
    public const DETAILS_TYPE_KEY = 'type';

    /** Key inside `details` that overrides the default retry semantics. */
    public const DETAILS_RETRYABLE_OVERRIDE_KEY = 'retryableOverride';

    /**
     * @codeCoverageIgnore
     */
    private function __construct() {}

    /**
     * Emits `details.retryableOverride` only for explicit values; ignores `type`/`retryableOverride` in $extraDetails.
     */
    public static function from(HandlerException $e, array $extraDetails = []): FailureInfo
    {
        $base = FailureInfo::fromThrowable($e);

        $details = $extraDetails;
        $details[self::DETAILS_TYPE_KEY] = $e->rawErrorType;
        if ($e->retryBehavior === RetryBehavior::Retryable) {
            $details[self::DETAILS_RETRYABLE_OVERRIDE_KEY] = true;
        } elseif ($e->retryBehavior === RetryBehavior::NonRetryable) {
            $details[self::DETAILS_RETRYABLE_OVERRIDE_KEY] = false;
        } else {
            unset($details[self::DETAILS_RETRYABLE_OVERRIDE_KEY]);
        }

        return new FailureInfo(
            message: $base->message,
            stackTrace: $base->stackTrace,
            metadata: [self::METADATA_TYPE_KEY => self::TYPE],
            detailsJson: \json_encode($details, \JSON_THROW_ON_ERROR),
            cause: $base->cause,
        );
    }

    public static function isHandlerError(FailureInfo $failure): bool
    {
        return ($failure->metadata[self::METADATA_TYPE_KEY] ?? null) === self::TYPE;
    }

    /**
     * Unknown wire values → {@see ErrorType::Unknown}. Null if not a
     * HandlerError or `details.type` missing/non-string.
     */
    public static function readErrorType(FailureInfo $failure): ?ErrorType
    {
        $raw = self::readRawErrorType($failure);
        if ($raw === null) {
            return null;
        }
        return ErrorType::tryFrom($raw) ?? ErrorType::Unknown;
    }

    /**
     * Raw `details.type` verbatim — for wire values not in {@see ErrorType}.
     */
    public static function readRawErrorType(FailureInfo $failure): ?string
    {
        $details = self::decodeDetails($failure);
        if ($details === null) {
            return null;
        }
        $raw = $details[self::DETAILS_TYPE_KEY] ?? null;
        return \is_string($raw) ? $raw : null;
    }

    /**
     * Explicit override: true / false / null (no override → use type-default).
     */
    public static function readRetryableOverride(FailureInfo $failure): ?bool
    {
        $details = self::decodeDetails($failure);
        if ($details === null) {
            return null;
        }
        $value = $details[self::DETAILS_RETRYABLE_OVERRIDE_KEY] ?? null;
        return \is_bool($value) ? $value : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function decodeDetails(FailureInfo $failure): ?array
    {
        if (!self::isHandlerError($failure) || $failure->detailsJson === null) {
            return null;
        }
        try {
            $decoded = \json_decode($failure->detailsJson, associative: true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        return \is_array($decoded) ? $decoded : null;
    }
}
