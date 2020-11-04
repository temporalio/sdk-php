<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal;

use PackageVersions\Versions;

final class Version
{
    /**
     * @var string
     */
    private const VENDOR_NAME = 'temporal/client';

    /**
     * @var string
     */
    private const FEATURE_VERSION = '1.0.0';

    /**
     * Library Version is a semver that represents the version of this Temporal
     * client library. This represent API changes visible to Temporal client
     * side library consumers. I.e. developers that are writing workflows.
     *
     * So every time we change API that can affect them we have to change this
     * number.
     *
     * @return string
     */
    public static function getLibraryVersion(): string
    {
        return Versions::getVersion(self::VENDOR_NAME);
    }

    /**
     * Feature Version is a semver that represents the feature set of this
     * Temporal client library support. This can be used for client capibility
     * check, on Temporal server, for backward compatibility.
     *
     * @return string
     */
    public static function getFeatureVersion(): string
    {
        return self::FEATURE_VERSION;
    }
}
