<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Marshaller\Type;

use Temporal\Client\Internal\Marshaller\MarshallerInterface;

/**
 * @psalm-type CallableTypeMatcher = \Closure(\ReflectionNamedType): ?string
 */
class Factory
{
    /**
     * @var string
     */
    private const ERROR_INVALID_TYPE = 'Mapping type must implement %s, but %s given';

    /**
     * @var array<CallableTypeMatcher>
     */
    private array $matchers;

    /**
     * @var MarshallerInterface
     */
    private MarshallerInterface $marshaller;

    /**
     * @param MarshallerInterface $marshaller
     * @param array<CallableTypeMatcher|DetectableTypeInterface> $matchers
     */
    public function __construct(MarshallerInterface $marshaller, array $matchers)
    {
        $this->marshaller = $marshaller;

        foreach ($this->createMatchers($matchers) as $matcher) {
            $this->matchers[] = $matcher;
        }

        foreach ($this->createMatchers($this->getDefaultMatchers()) as $matcher) {
            $this->matchers[] = $matcher;
        }
    }

    /**
     * @param iterable<CallableTypeMatcher|DetectableTypeInterface> $matchers
     * @return iterable<CallableTypeMatcher>
     */
    private function createMatchers(iterable $matchers): iterable
    {
        foreach ($matchers as $matcher) {
            if ($matcher instanceof \Closure) {
                yield $matcher;
                continue;
            }

            yield static function (\ReflectionNamedType $type) use ($matcher): ?string {
                return $matcher::match($type) ? $matcher : null;
            };
        }
    }

    /**
     * @return iterable<class-string<DetectableTypeInterface>>
     */
    private function getDefaultMatchers(): iterable
    {
        yield DateTimeType::class;
        yield DateIntervalType::class;
        yield ArrayType::class;
        yield ObjectType::class;
    }

    /**
     * @param class-string<TypeInterface> $type
     * @param array $args
     * @return TypeInterface|null
     */
    public function create(string $type, array $args): ?TypeInterface
    {
        if (! \is_subclass_of($type, TypeInterface::class)) {
            throw new \InvalidArgumentException(\sprintf(self::ERROR_INVALID_TYPE, TypeInterface::class, $type));
        }

        return new $type($this->marshaller, ...$args);
    }

    /**
     * @param \ReflectionType|null $type
     * @return class-string<TypeInterface>|null
     */
    public function detect(?\ReflectionType $type): ?string
    {
        /**
         * - Union types ({@see \ReflectionUnionType}) cannot be uniquely determined.
         * - The {@see null} type is an alias of "mixed" type.
         */
        if (! $type instanceof \ReflectionNamedType) {
            return null;
        }

        foreach ($this->matchers as $matcher) {
            if ($result = $matcher($type)) {
                return $result;
            }
        }

        return null;
    }
}
