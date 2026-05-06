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
 * @internal
 */
final class Metadata
{
    public const KIND_KEY = '_rr_nexus_kind';
    public const KIND_ASYNC = 'async';
    public const LINKS_KEY = '_rr_nexus_links';

    /**
     * @codeCoverageIgnore
     */
    private function __construct() {}
}
