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
use Temporal\Internal\Marshaller\MarshallingRule;

/**
 * Force the value to be an associative array (object) on serialization.
 *
 * @extends Type<object>
 */
class AssocArrayType extends Type
{
    /**
     * @var string
     */
    private const ERROR_INVALID_TYPE = 'Passed value must be a type of array, but %s given';

    private ?TypeInterface $type = null;

    /**
     *
     * @throws \ReflectionException
     */
    public function __construct(MarshallerInterface $marshaller, MarshallingRule|string|null $typeOrClass = null)
    {
        if ($typeOrClass !== null) {
            $this->type = $this->ofType($marshaller, $typeOrClass);
        }

        parent::__construct($marshaller);
    }

    /**
     * @psalm-assert array $value
     * @psalm-assert array $current
     * @param mixed $value
     * @param mixed $current
     */
    public function parse($value, $current): array
    {
        \is_array($value) or throw new \InvalidArgumentException(
            \sprintf(self::ERROR_INVALID_TYPE, \get_debug_type($value)),
        );

        if ($this->type) {
            $result = [];

            foreach ($value as $i => $item) {
                $result[$i] = $this->type->parse($item, $current[$i] ?? null);
            }

            return $result;
        }

        return $value;
    }

    public function serialize($value): object
    {
        if ($this->type) {
            $result = [];

            foreach ($value as $i => $item) {
                $result[$i] = $this->type->serialize($item);
            }

            return (object) $result;
        }

        if (\is_array($value)) {
            return (object) $value;
        }

        // Convert iterable to array
        $result = [];
        foreach ($value as $i => $item) {
            $result[$i] = $item;
        }
        return (object) $result;
    }
}
