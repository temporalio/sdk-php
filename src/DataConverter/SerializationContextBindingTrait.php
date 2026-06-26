<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\DataConverter;

trait SerializationContextBindingTrait
{
    private ?DataConverterInterface $converter = null;
    private ?SerializationContext $serializationContext = null;
    private ?DataConverterInterface $boundConverter = null;

    public function setDataConverter(DataConverterInterface $converter): void
    {
        $this->converter = $converter;
        $this->boundConverter = null;
    }

    public function setSerializationContext(?SerializationContext $context): void
    {
        $this->serializationContext = $context;
        $this->boundConverter = null;
    }

    public function getSerializationContext(): ?SerializationContext
    {
        return $this->serializationContext;
    }

    private function converter(): DataConverterInterface
    {
        if ($this->converter === null) {
            throw new \LogicException('DataConverter is not set.');
        }

        return $this->boundConverter ??= SerializationContextBinder::bind($this->converter, $this->serializationContext);
    }
}
