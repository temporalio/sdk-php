<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus;

use Temporal\Internal\Support\DateInterval;
use Temporal\Nexus\Exception\InvalidArgumentException;

/**
 * Well-known Nexus header names + small utilities. Values are wire-level keys.
 * Use {@see self::get()} for case-insensitive lookup against lowercased maps.
 *
 * @see https://github.com/nexus-rpc/api/blob/main/SPEC.md
 */
final class Header
{
    /** Total time per HTTP request, format `<number><ms|s|m>`. */
    public const REQUEST_TIMEOUT = 'Request-Timeout';

    /** Total time per Nexus operation (may span callbacks). Same format as {@see self::REQUEST_TIMEOUT}. */
    public const OPERATION_TIMEOUT = 'Operation-Timeout';

    /** @deprecated Use {@see self::OPERATION_TOKEN}. */
    public const OPERATION_ID = 'Nexus-Operation-Id';

    /** Async operation token returned from StartOperation. */
    public const OPERATION_TOKEN = 'Nexus-Operation-Token';

    /** IMF-fixdate (RFC 9110 §5.6.7) timestamp; optional on async callback POST (defaults to reception time). */
    public const OPERATION_START_TIME = 'Nexus-Operation-Start-Time';

    /** RFC 3339 ms-precision timestamp; required on async callback POST. */
    public const OPERATION_CLOSE_TIME = 'Nexus-Operation-Close-Time';

    /**
     * Terminal state on callback POST. One of {@see OperationState} values.
     * Deprecated on `424 Failed Dependency` — modern callers read the body.
     */
    public const OPERATION_STATE = 'Nexus-Operation-State';

    /** Caller-provided opaque non-empty id for retry de-duplication. */
    public const REQUEST_ID = 'Nexus-Request-Id';

    /** RFC 5988 link header; `<uri>; type="..."`. May repeat. */
    public const LINK = 'Nexus-Link';

    /**
     * Prefix on caller headers that the handler must forward (with prefix
     * stripped) on the async callback POST. Compare case-insensitively.
     */
    public const CALLBACK_PREFIX = 'Nexus-Callback-';

    /** URL the handler POSTs to on async-operation terminal state. */
    public const CALLBACK_URL = self::CALLBACK_PREFIX . 'Url';

    /** Inbound name of the opaque auth token; outbound it is sent as `Token: ...` (secret). */
    public const CALLBACK_TOKEN = self::CALLBACK_PREFIX . 'Token';

    /**
     * @deprecated Legacy. Prefer {@see \Temporal\Nexus\Exception\HandlerException}'s {@see RetryBehavior}.
     */
    public const RETRYABLE = 'Nexus-Request-Retryable';

    /** Standard HTTP `Content-Type` header. */
    public const CONTENT_TYPE = 'Content-Type';

    /** Required `Content-Type` for spec JSON envelopes (see {@see \Temporal\Nexus\OperationInfo} and the Nexus `Failure` shape). */
    public const CONTENT_TYPE_JSON = 'application/json';

    /**
     * @codeCoverageIgnore
     */
    private function __construct() {}

    /**
     * Case-insensitive lookup over a lowercase-normalized header map.
     *
     * @param array<string, string> $headers Must be lowercase-normalized.
     */
    public static function get(array $headers, string $name): ?string
    {
        return $headers[\strtolower($name)] ?? null;
    }

    /**
     * Parse `"30s"` / `"250ms"` / `"2m"`. Empty → null.
     *
     * @throws InvalidArgumentException
     */
    public static function parseTimeout(string $value): ?\DateInterval
    {
        $trimmed = \trim($value);
        if ($trimmed === '') {
            return null;
        }

        if (!\preg_match('/^[+-]?\d/', $trimmed)) {
            throw new InvalidArgumentException("Invalid Nexus timeout '{$value}'");
        }

        try {
            return DateInterval::parse($trimmed, DateInterval::FORMAT_SECONDS);
        } catch (\Throwable $e) {
            throw new InvalidArgumentException("Invalid Nexus timeout '{$value}'", 0, $e);
        }
    }

    /**
     * `$now + parseTimeout($value)`. Empty → null. `$now` defaults to UTC now.
     *
     * @throws InvalidArgumentException
     */
    public static function deadlineFromTimeout(
        string $value,
        ?\DateTimeImmutable $now = null,
    ): ?\DateTimeImmutable {
        $interval = self::parseTimeout($value);
        if ($interval === null) {
            return null;
        }
        return ($now ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->add($interval);
    }
}
