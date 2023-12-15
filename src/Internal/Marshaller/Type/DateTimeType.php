<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Marshaller\Type;

use DateTimeInterface;
use Google\Protobuf\Timestamp;
use JetBrains\PhpStorm\Pure;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Marshaller\MarshallingRule;
use Temporal\Internal\Support\DateTime;
use Temporal\Internal\Support\Inheritance;

/**
 * @extends Type<Timestamp|non-empty-string>
 */
class DateTimeType extends Type implements DetectableTypeInterface, RuleFactoryInterface
{
    /**
     * @var string
     */
    private string $format;
    /**
     * @var class-string<DateTimeInterface>
     */
    private string $class;

    /**
     * @param MarshallerInterface $marshaller
     * @param class-string<DateTimeInterface>|null $class
     * @param string $format
     */
    #[Pure]
    public function __construct(
        MarshallerInterface $marshaller,
        ?string $class = null,
        string $format = \DateTimeInterface::RFC3339,
    ) {
        $class ??= DateTimeInterface::class;
        $this->format = $format;

        parent::__construct($marshaller);
        $this->class = $class;
    }

    /**
     * {@inheritDoc}
     */
    public static function match(\ReflectionNamedType $type): bool
    {
        return !$type->isBuiltin() && Inheritance::implements($type->getName(), \DateTimeInterface::class);
    }

    /**
     * {@inheritDoc}
     */
    public static function makeRule(\ReflectionProperty $property): ?MarshallingRule
    {
        $type = $property->getType();

        if (!$type instanceof \ReflectionNamedType || !\is_subclass_of($type->getName(), \DateTimeInterface::class)) {
            return null;
        }

        return $type->allowsNull()
            ? new MarshallingRule(
                $property->getName(),
                NullableType::class,
                new MarshallingRule(type: self::class, of: $type->getName()),
            )
            : new MarshallingRule($property->getName(), self::class, $type->getName());
    }

    /**
     * {@inheritDoc}
     */
    public function parse($value, $current): \DateTimeInterface
    {
        return DateTime::parse($value, class: $this->class);
    }

    public function serialize($value): Timestamp|string
    {
        $datetime = DateTime::parse($value);
        return match ($this->format) {
            Timestamp::class => (new Timestamp())
                ->setSeconds($datetime->getTimestamp())
                ->setNanos((int)$datetime->format('u') * 1000),
            default => $datetime->format($this->format),
        };
    }
}
