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
use Temporal\Nexus\OperationState;

/**
 * Canonical mapping between {@see OperationException} and the Nexus-spec
 * `OperationError` failure shape. Use {@see self::from()} on the producing
 * side and `read*` helpers on the consuming side.
 *
 * @see https://github.com/nexus-rpc/api/blob/main/SPEC.md
 */
final class OperationErrorFailure
{
    /** Value of `metadata.type` that marks an OperationError failure. */
    public const TYPE = 'nexus.OperationError';

    /** Key in the `metadata` map that carries the failure-shape discriminator. */
    public const METADATA_TYPE_KEY = 'type';

    /** Key inside `details` that carries the operation's terminal state. */
    public const DETAILS_STATE_KEY = 'state';

    /**
     * @codeCoverageIgnore
     */
    private function __construct() {}

    /**
     * Package an {@see OperationException} into the wire `OperationError` shape.
     *
     * @param array<string, mixed> $extraDetails Merged into `details`. The
     *        `state` key is not overridable.
     */
    public static function from(OperationException $e, array $extraDetails = []): FailureInfo
    {
        $base = FailureInfo::fromThrowable($e);

        $details = $extraDetails;
        $details[self::DETAILS_STATE_KEY] = $e->state->value;

        return new FailureInfo(
            message: $base->message,
            stackTrace: $base->stackTrace,
            metadata: [self::METADATA_TYPE_KEY => self::TYPE],
            detailsJson: \json_encode($details, \JSON_THROW_ON_ERROR),
            cause: $base->cause,
        );
    }

    public static function isOperationError(FailureInfo $failure): bool
    {
        return ($failure->metadata[self::METADATA_TYPE_KEY] ?? null) === self::TYPE;
    }

    /**
     * Returns null when not an OperationError, missing `details`, or
     * `details.state` is not `failed` / `canceled`.
     */
    public static function readState(FailureInfo $failure): ?OperationState
    {
        if (!self::isOperationError($failure) || $failure->detailsJson === null) {
            return null;
        }

        try {
            $details = \json_decode($failure->detailsJson, associative: true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!\is_array($details)) {
            return null;
        }

        $state = $details[self::DETAILS_STATE_KEY] ?? null;
        if (!\is_string($state)) {
            return null;
        }

        // @codeCoverageIgnoreStart
        return match ($state) {
            OperationState::Failed->value   => OperationState::Failed,
            OperationState::Canceled->value => OperationState::Canceled,
            default                         => null,
        };
        // @codeCoverageIgnoreEnd
    }
}
