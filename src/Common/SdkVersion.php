<?php

declare(strict_types=1);

namespace Temporal\Common;

use Composer\InstalledVersions;

final class SdkVersion
{
    public const VERSION_REGX = '/^(\d++\.\d++\.\d++(?:-[\\w\\-.]+)?)/';
    public const PACKAGE_NAME = 'temporal/sdk';

    public static function getSdkVersion(): string
    {
        $version = InstalledVersions::getVersion(SdkVersion::PACKAGE_NAME);

        if ($version === null || \preg_match(self::VERSION_REGX, $version, $matches) !== 1) {
            return '';
        }

        return $matches[1];
    }
}
