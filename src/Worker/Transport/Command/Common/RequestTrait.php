<?php

declare(strict_types=1);

namespace Temporal\Worker\Transport\Command\Common;

use Temporal\DataConverter\ValuesInterface;
use Temporal\Interceptor\Header;
use Temporal\Interceptor\HeaderInterface;

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

    public function getHeader(): Header
    {
        return $this->header;
    }

    public function withHeader(HeaderInterface $header): self
    {
        $clone = clone $this;
        $clone->header = $header;
        return $clone;
    }
}
