<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Marshaller\Fixture;

use Ramsey\Uuid\UuidInterface;

final class PropertyType
{
    public string $string;
    public int $int;
    public float $float;
    public bool $bool;
    public array $array;
    public ?string $nullableString;
    public ?int $nullableInt;
    public ?float $nullableFloat;
    public ?bool $nullableBool;
    public ?array $nullableArray;
    public UuidInterface $uuid;
    public ?UuidInterface $nullableUuid;
}
