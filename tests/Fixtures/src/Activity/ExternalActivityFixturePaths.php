<?php

declare(strict_types=1);

namespace Temporal\Tests\Activity;

final class ExternalActivityFixturePaths
{
    private const PREFIX = 'temporal-php-sdk-external-activity';

    public static function tokenPath(): string
    {
        return \sys_get_temp_dir() . '/' . self::PREFIX . '-token';
    }

    public static function idPath(): string
    {
        return \sys_get_temp_dir() . '/' . self::PREFIX . '-id';
    }
}
