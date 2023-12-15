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
 * @extends Type<mixed>
 */
class NullableType extends Type
{
    /**
     * @var TypeInterface|null
     */
    private ?TypeInterface $type = null;

    /**
     * @param MarshallerInterface $marshaller
     * @param MarshallingRule|string|null $typeOrClass
     *
     * @throws \ReflectionException
     */
    public function __construct(MarshallerInterface $marshaller, MarshallingRule|string $typeOrClass = null)
    {
        if ($typeOrClass !== null) {
            $this->type = $this->ofType($marshaller, $typeOrClass);
        }

        parent::__construct($marshaller);
    }

    /**
     * @param mixed $value
     * @param mixed $current
     * @return mixed
     */
    public function parse($value, $current)
    {
        if ($value === null) {
            return null;
        }

        if ($this->type) {
            return $this->type->parse($value, $current);
        }

        return $value;
    }

    public function serialize($value)
    {
        if ($value === null) {
            return null;
        }

        if ($this->type) {
            return $this->type->serialize($value);
        }

        return $value;
    }
}
