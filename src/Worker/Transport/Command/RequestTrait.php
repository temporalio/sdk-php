<?php

declare(strict_types=1);

namespace Temporal\Worker\Transport\Command;

use Temporal\DataConverter\ValuesInterface;

trait RequestTrait
{
    public function getName(): string
    {
        return $this->name;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function getPayloads(): ValuesInterface
    {
        return $this->payloads;
    }

    public function getHeader(): object
    {
        return $this->header;
    }
}
