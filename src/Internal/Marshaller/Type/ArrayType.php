<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Marshaller\Type;

use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Support\Inheritance;

class ArrayType extends Type implements DetectableTypeInterface
{
    /**
     * @var string
     */
    private const ERROR_INVALID_TYPE = 'Passed value must be a type of array, but %s given';

    /**
     * @var TypeInterface|null
     */
    private ?TypeInterface $type = null;

    /**
     * @param MarshallerInterface $marshaller
     * @param string|null $typeOrClass
     * @throws \ReflectionException
     */
    public function __construct(MarshallerInterface $marshaller, string $typeOrClass = null)
    {
        if ($typeOrClass !== null) {
            $this->type = Inheritance::implements($typeOrClass, TypeInterface::class)
                ? new $typeOrClass($marshaller)
                : new ObjectType($marshaller, $typeOrClass)
            ;
        }

        parent::__construct($marshaller);
    }

    /**
     * {@inheritDoc}
     */
    public static function match(\ReflectionNamedType $type): bool
    {
        return $type->getName() === 'array';
    }

    /**
     * @param array $value
     * @param array $current
     * @return array|mixed
     */
    public function parse($value, $current)
    {
        if (!\is_array($value)) {
            throw new \InvalidArgumentException(\sprintf(self::ERROR_INVALID_TYPE, \get_debug_type($value)));
        }

        if ($this->type) {
            $result = [];

            foreach ($value as $i => $item) {
                $result[] = $this->type->parse($item, $current[$i] ?? null);
            }

            return $result;
        }

        return $value;
    }

    /**
     * @param array $value
     * @return array
     */
    public function serialize($value): array
    {
        if ($this->type) {
            $result = [];

            foreach ($value as $i => $item) {
                $result[$i] = $this->type->serialize($item);
            }

            return $result;
        }

        return $value;
    }
}
