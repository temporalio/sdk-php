<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Marshaller\Type;

use stdClass;
use Temporal\Internal\Marshaller\MarshallerInterface;

/**
 * @template TClass of object
 * @extends Type<array>
 */
class OneOfType extends Type
{
    /**
     * @param class-string<TClass>|null $parentClass
     * @param array<non-empty-string, class-string> $cases
     */
    public function __construct(
        MarshallerInterface $marshaller,
        private ?string $parentClass = null,
        private array $cases = [],
        private bool $nullable = true,
    ) {
        parent::__construct($marshaller);
    }

    /**
     * {@inheritDoc}
     */
    public function parse(mixed $value, mixed $current): ?object
    {
        if (\is_object($value)) {
            return $value;
        }

        if (!\is_array($value)) {
            throw new \InvalidArgumentException(\sprintf(
                'Passed value must be a type of array, but %s given.',
                \get_debug_type($value),
            ));
        }

        $dtoClass = null;
        // Detect class
        foreach ($this->cases as $field => $class) {
            if (!isset($value[$field])) {
                continue;
            }

            $value = $value[$field];
            $dtoClass = $class;
            break;
        }

        if ($dtoClass === null) {
            $this->nullable or throw new \InvalidArgumentException(\sprintf(
                'Unable to detect OneOf case for non-nullable type%s.',
                $this->parentClass ? " `{$this->parentClass}`" : '',
            ));

            return null;
        }

        $dto = \is_object($current) && $current::class === $dtoClass
            ? $current
            : $this->emptyInstance($dtoClass);

        if ($dtoClass === stdClass::class) {
            foreach ($value as $key => $val) {
                $current->$key = $val;
            }

            return $current;
        }

        return $this->marshaller->unmarshal($value, $dto);
    }

    /**
     * {@inheritDoc}
     */
    public function serialize(mixed $value): array
    {
        if ($this->nullable && $value === null) {
            return [];
        }

        \is_object($value) or throw new \InvalidArgumentException(\sprintf(
            'Passed value must be a type of object, but %s given.',
            \get_debug_type($value),
        ));

        foreach ($this->cases as $field => $class) {
            if ($value::class === $class) {
                return [$field => $this->marshaller->marshal($value)];
            }
        }
    }

    /**
     * @template T
     *
     * @param class-string<T> $class
     *
     * @return T
     *
     * @throws \ReflectionException
     */
    protected function emptyInstance(string $class): object
    {
        return (new \ReflectionClass($class))->newInstanceWithoutConstructor();
    }
}
