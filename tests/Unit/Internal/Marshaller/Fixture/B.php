<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Marshaller\Fixture;

final class B
{
    public string $code;
    public ?string $description;

    public function __construct(string $code, ?string $description = null)
    {
        $this->code = $code;
        $this->description = $description;
    }
}
