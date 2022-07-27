<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Marshaller\Fixture;

final class A
{
    public string $x;
    public ?B $b;

    public function __construct(string $x, ?B $b = null)
    {
        $this->x = $x;
        $this->b = $b;
    }
}
