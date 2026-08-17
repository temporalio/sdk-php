<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\DataConverter;

trait DataConverterAwareTrait
{
    private ?DataConverterInterface $converter = null;
    private ?SerializationContext $serializationContext = null;
    private ?DataConverterInterface $effectiveConverter = null;

    public function setDataConverter(DataConverterInterface $converter): void
    {
        $this->converter = $converter;
        $this->effectiveConverter = null;
    }

    public function getDataConverter(): ?DataConverterInterface
    {
        return $this->converter;
    }

    public function getSerializationContext(): ?SerializationContext
    {
        return $this->serializationContext;
    }

    public function setSerializationContext(?SerializationContext $context): void
    {
        $this->serializationContext = $context;
        $this->effectiveConverter = null;
    }

    public function withSerializationContext(?SerializationContext $context): static
    {
        $clone = clone $this;
        $clone->serializationContext = $context;
        $clone->effectiveConverter = null;

        return $clone;
    }

    private function converter(): DataConverterInterface
    {
        if ($this->converter === null) {
            throw new \LogicException('DataConverter is not set.');
        }

        if ($this->effectiveConverter !== null) {
            return $this->effectiveConverter;
        }

        $converter = $this->converter;
        if ($this->serializationContext !== null && $converter instanceof SerializationContextAwareInterface) {
            $converter = $converter->withSerializationContext($this->serializationContext);
        }

        return $this->effectiveConverter = $converter;
    }
}
