<?php

declare(strict_types=1);

namespace Temporal\Testing;

final class TemporalServer
{
    private const DEFAULT_ADDRESS = '127.0.0.1:7233';

    /** @var non-empty-string|null */
    private static ?string $address = null;

    /**
     * @param non-empty-string $address
     */
    public static function setAddress(string $address): void
    {
        self::$address = $address;
    }

    /**
     * @return non-empty-string
     */
    public static function address(): string
    {
        if (self::$address === null) {
            $fromEnv = \getenv('TEMPORAL_ADDRESS');
            self::$address = \is_string($fromEnv) && $fromEnv !== '' ? $fromEnv : self::DEFAULT_ADDRESS;
        }

        return self::$address;
    }
}
