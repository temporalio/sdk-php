<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Nexus\RoadRunner;

/**
 * Wire-protocol marker keys used in payload metadata between the PHP worker
 * and RoadRunner for Nexus dispatch. Not part of the Nexus spec — these are
 * the side-channel that RR uses to distinguish sync vs async results and to
 * carry handler links across the proto boundary.
 *
 * @internal
 */
final class Metadata
{
    /**
     * Marks payload data as a Nexus operation token (async start). When this
     * key is present in payload metadata, RR treats `payload.data` as the
     * operation token rather than as a serialized return value.
     */
    public const KIND_KEY = '_rr_nexus_kind';

    /** Value stored under {@see self::KIND_KEY} for async-start results. */
    public const KIND_ASYNC = 'async';

    /**
     * Carries handler-collected links as a JSON array `[{url,type},...]`.
     * RR forwards them onto the StartOperationResponse on the wire.
     */
    public const LINKS_KEY = '_rr_nexus_links';

    /**
     * @codeCoverageIgnore
     */
    private function __construct() {}
}
