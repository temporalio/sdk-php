<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Marshaller;

use Temporal\Internal\Marshaller\Type\ArrayType;
use Temporal\Internal\Marshaller\Type\DateIntervalType;
use Temporal\Internal\Marshaller\Type\DateTimeType;
use Temporal\Internal\Marshaller\Type\DetectableTypeInterface;
use Temporal\Internal\Marshaller\Type\EncodedCollectionType;
use Temporal\Internal\Marshaller\Type\EnumType;
use Temporal\Internal\Marshaller\Type\ObjectType;
use Temporal\Internal\Marshaller\Type\OneOfType;
use Temporal\Internal\Marshaller\Type\RuleFactoryInterface as TypeRuleFactoryInterface;
use Temporal\Internal\Marshaller\Type\TypeInterface;
use Temporal\Internal\Marshaller\Type\UuidType;

/**
 * @psalm-type CallableTypeMatcher = \Closure(\ReflectionNamedType): ?string
 * @psalm-type CallableTypeDtoMatcher = \Closure(\ReflectionProperty): ?MarshallingRule
 */
class TypeFactory implements RuleFactoryInterface
{
    /**
     * @var string
     */
    private const ERROR_INVALID_TYPE = 'Mapping type must implement %s, but %s given';

    /**
     * @var list<CallableTypeMatcher>
     */
    private array $matchers = [];

    /**
     * @var list<TypeRuleFactoryInterface>
     */
    private array $typeDtoMatchers = [];

    /**
     * @var MarshallerInterface
     */
    private MarshallerInterface $marshaller;

    /**
     * @param MarshallerInterface $marshaller
     * @param array<CallableTypeMatcher|DetectableTypeInterface|TypeRuleFactoryInterface> $matchers
     */
    public function __construct(MarshallerInterface $marshaller, array $matchers)
    {
        $this->marshaller = $marshaller;

        $this->createMatchers($matchers);
        $this->createMatchers($this->getDefaultMatchers());
    }

    /**
     * {@inheritDoc}
     */
    public function create(string $type, array $args): ?TypeInterface
    {
        if (!\is_subclass_of($type, TypeInterface::class)) {
            throw new \InvalidArgumentException(\sprintf(self::ERROR_INVALID_TYPE, TypeInterface::class, $type));
        }

        return new $type($this->marshaller, ...$args);
    }

    /**
     * {@inheritDoc}
     */
    public function detect(?\ReflectionType $type): ?string
    {
        /**
         * - Union types ({@see \ReflectionUnionType}) cannot be uniquely determined.
         * - The {@see null} type is an alias of "mixed" type.
         */
        if (!$type instanceof \ReflectionNamedType) {
            return null;
        }

        foreach ($this->matchers as $matcher) {
            $result = $matcher($type);
            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function makeRule(\ReflectionProperty $property): ?MarshallingRule
    {
        foreach ($this->typeDtoMatchers as $matcher) {
            $result = $matcher::makeRule($property);
            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    /**
     * @param iterable<CallableTypeMatcher|DetectableTypeInterface|TypeRuleFactoryInterface> $matchers
     */
    private function createMatchers(iterable $matchers): void
    {
        foreach ($matchers as $matcher) {
            if ($matcher instanceof \Closure) {
                $this->matchers[] = $matcher;
                continue;
            }

            if (\is_subclass_of($matcher, TypeRuleFactoryInterface::class, true)) {
                $this->typeDtoMatchers[] = $matcher;
            }

            if (\is_subclass_of($matcher, DetectableTypeInterface::class, true)) {
                $this->matchers[] = static fn (\ReflectionNamedType $type): ?string => $matcher::match($type)
                    ? $matcher
                    : null;
            }
        }
    }

    /**
     * @return iterable<class-string<DetectableTypeInterface>>
     */
    private function getDefaultMatchers(): iterable
    {
        if (PHP_VERSION_ID >= 80104) {
            yield EnumType::class;
        }

        yield DateTimeType::class;
        yield DateIntervalType::class;
        yield UuidType::class;
        yield ArrayType::class;
        yield EncodedCollectionType::class;
        yield OneOfType::class;
        yield ObjectType::class;
    }
}
