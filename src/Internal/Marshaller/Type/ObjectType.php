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

class ObjectType extends Type implements DetectableTypeInterface
{
    /**
     * @var \ReflectionClass
     */
    private \ReflectionClass $reflection;

    /**
     * @param MarshallerInterface $marshaller
     * @param string|null $class
     * @throws \ReflectionException
     */
    public function __construct(MarshallerInterface $marshaller, string $class = null)
    {
        $this->reflection = new \ReflectionClass($class ?? \stdClass::class);

        parent::__construct($marshaller);
    }

    /**
     * {@inheritDoc}
     */
    public static function match(\ReflectionNamedType $type): bool
    {
        return !$type->isBuiltin();
    }

    /**
     * {@inheritDoc}
     */
    public function parse($value, $current): object
    {
        if (is_object($value)) {
            return $value;
        }

        if ($current === null) {
            $current = $this->instance((array)$value);
        }

        return $this->marshaller->unmarshal($value, $current);
    }

    /**
     * {@inheritDoc}
     */
    public function serialize($value): array
    {
        return $this->marshaller->marshal($value);
    }

    /**
     * @param array $data
     * @return object
     * @throws \ReflectionException
     */
    protected function instance(array $data): object
    {
        return $this->marshaller->unmarshal($data, $this->reflection->newInstanceWithoutConstructor());
    }
}
