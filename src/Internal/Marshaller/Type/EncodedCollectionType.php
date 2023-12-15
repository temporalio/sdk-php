<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Marshaller\Type;

use Google\Protobuf\Internal\Message;
use ReflectionNamedType;
use Temporal\Api\Common\V1\Header;
use Temporal\Api\Common\V1\Memo;
use Temporal\Api\Common\V1\Payloads;
use Temporal\Api\Common\V1\SearchAttributes;
use Temporal\DataConverter\EncodedCollection;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Marshaller\MarshallingRule;
use Temporal\Internal\Support\Inheritance;

/**
 * Read only type.
 * @extends Type<array|Message>
 */
final class EncodedCollectionType extends Type implements DetectableTypeInterface, RuleFactoryInterface
{
    /**
     * @param class-string<Message>|null $marshalTo
     */
    public function __construct(MarshallerInterface $marshaller, private ?string $marshalTo = null)
    {
        parent::__construct($marshaller);
    }

    public static function match(\ReflectionNamedType $type): bool
    {
        return !$type->isBuiltin() &&
            Inheritance::extends($type->getName(), EncodedCollection::class);
    }

    public static function makeRule(\ReflectionProperty $property): ?MarshallingRule
    {
        $type = $property->getType();

        if (!$type instanceof ReflectionNamedType || !self::match($type)) {
            return null;
        }

        return new MarshallingRule($property->getName(), self::class, $type->getName());
    }

    /**
     * @psalm-assert string $value
     */
    public function parse(mixed $value, mixed $current): EncodedCollection
    {
        return match (true) {
            $value === null => EncodedCollection::empty(),
            \is_array($value) => EncodedCollection::fromValues($value),
            $value instanceof EncodedCollection => $value,
            default => throw new \InvalidArgumentException('Unsupported value type'),
        };
    }

    public function serialize(mixed $value): array|Message
    {
        if (!$value instanceof EncodedCollection) {
            throw new \InvalidArgumentException(\sprintf('Unsupported value type %s', \get_debug_type($value)));
        }

        if ($this->marshalTo === null) {
            return $value->getValues();
        }

        $payloads = $value->toPayloadArray();

        /** @var Message $message */
        return match ($this->marshalTo) {
            SearchAttributes::class => (new SearchAttributes())->setIndexedFields($payloads),
            Memo::class => (new Memo())->setFields($payloads),
            Payloads::class => (new Payloads())->setPayloads($payloads),
            Header::class => (new Header())->setFields($payloads),
            default => throw new \InvalidArgumentException('Unsupported target type.'),
        };
    }
}
