<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Support;

abstract class DataTransferObject implements \JsonSerializable
{
    /**
     * @var string
     */
    private const PREFIX_GETTER = 'get';

    /**
     * @var string
     */
    private const PREFIX_SETTER = 'set';

    /**
     * @var string
     */
    private const ERROR_INVALID_PROPERTIES = 'Invalid "%s" properties format given';

    /**
     * @var string
     */
    private const ERROR_INACCESSIBLE_PROPERTY = 'Property "%s" not not accessible or not defined in "%s"';

    /**
     * @param mixed $options
     * @return static
     */
    public static function new($properties = null): self
    {
        switch (true) {
            case $properties === null:
                return new static();

            case \is_iterable($properties):
                $instance = new static();
                $instance->fromArray(Iter::toArray($properties));

                return $instance;

            case $properties instanceof self:
                return $properties;

            default:
                throw new \InvalidArgumentException(\sprintf(self::ERROR_INVALID_PROPERTIES, static::class));
        }
    }

    /**
     * @param array $properties
     */
    public function fromArray(array $properties): void
    {
        foreach ($properties as $name => $value) {
            $setter = self::PREFIX_SETTER . \ucfirst($name);

            if (! \method_exists($this, $setter)) {
                $error = \sprintf(self::ERROR_INACCESSIBLE_PROPERTY, $name, static::class);

                throw new \InvalidArgumentException($error);
            }

            $this->$setter($value);
        }
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        $result = [];

        foreach (\get_object_vars($this) as $name => $value) {
            $getter = self::PREFIX_GETTER . \ucfirst($name);

            $value = \method_exists($this, $getter) ? $this->$getter() : $value;

            if ($value === null) {
                continue;
            }

            if ($value instanceof self) {
                $value = $value->toArray();
            }

            $result[$name] = $value;
        }

        return $result;
    }

    /**
     * @param array $array
     * @return array
     */
    protected function arrayKeysToUpper(array $array): array
    {
        $result = [];

        foreach ($array as $name => $value) {
            $result[\ucfirst($name)] = $value;
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function jsonSerialize(): string
    {
        return Json::encode($this->toArray());
    }
}
