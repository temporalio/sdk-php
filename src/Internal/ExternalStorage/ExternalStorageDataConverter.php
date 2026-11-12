<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\ExternalStorage;

use Temporal\Api\Common\V1\Payload;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\RawValue;
use Temporal\DataConverter\SerializationContext;
use Temporal\DataConverter\SerializationContextAwareInterface;
use Temporal\DataConverter\Type;

/**
 * @internal
 */
final class ExternalStorageDataConverter implements DataConverterInterface, SerializationContextAwareInterface
{
    public function __construct(
        private DataConverterInterface $delegate,
        private readonly ExternalStorageRunner $storage,
    ) {}

    public function fromPayload(Payload $payload, mixed $type): mixed
    {
        $resolved = Type::create($type);
        if ($resolved->isClass() && $resolved->getName() === RawValue::class) {
            return $this->delegate->fromPayload($payload, $type);
        }

        return $this->delegate->fromPayload($this->storage->retrieve($payload), $type);
    }

    public function toPayload(mixed $value): Payload
    {
        return $this->delegate->toPayload($value);
    }

    public function getSerializationContext(): ?SerializationContext
    {
        return $this->delegate instanceof SerializationContextAwareInterface
            ? $this->delegate->getSerializationContext()
            : null;
    }

    public function withSerializationContext(?SerializationContext $context): static
    {
        if (!$this->delegate instanceof SerializationContextAwareInterface) {
            return $this;
        }

        $clone = clone $this;
        $clone->delegate = $this->delegate->withSerializationContext($context);

        return $clone;
    }
}
