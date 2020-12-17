<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal;

use Composer\InstalledVersions;

final class Version
{
    /**
     * @var string
     */
    private const DEFAULT_FEATURE_VERSION = '1.0.0';

    /**
     * @var string
     */
    private const DEFAULT_LIBRARY_VERSION = '1.0.0';

    /**
     * @var string|null
     */
    private static ?string $libraryVersion = null;

    /**
     * @var string|null
     */
    private static ?string $featureVersion = null;

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
        if (self::$libraryVersion === null) {
            try {
                self::$libraryVersion = InstalledVersions::getRootPackage()['reference'];
            } catch (\OutOfBoundsException $e) {
                self::$libraryVersion = self::DEFAULT_LIBRARY_VERSION;
            }
        }

        return self::$libraryVersion;
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
        return self::$featureVersion ??= self::DEFAULT_FEATURE_VERSION;
    }
}
