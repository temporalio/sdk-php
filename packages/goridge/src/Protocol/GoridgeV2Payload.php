<?php

/**
 * This file is part of Goridge package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\Goridge\Protocol;

final class GoridgeV2Payload
{
    /**
     * Must be set when data is json (default value).
     */
    public const TYPE_JSON = 0;

    /**
     * Must be set when no data to be sent.
     */
    public const TYPE_EMPTY = 2;

    /**
     * Must be set when data binary data.
     */
    public const TYPE_RAW = 4;

    /**
     * Must be set when data is error string or structure.
     */
    public const TYPE_ERROR = 8;

    /**
     * Defines that associated data must be treated as control data.
     */
    public const TYPE_CONTROL = 16;
}
