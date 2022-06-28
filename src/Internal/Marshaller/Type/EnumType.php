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

use function is_array;

class EnumType extends Type implements DetectableTypeInterface
{
    private string $classFQCN;

    public function __construct(MarshallerInterface $marshaller, string $class = null)
    {
        if (PHP_VERSION_ID < 80104) {
            throw new \RuntimeException('Enums are not available in this version of PHP');
        }

        if ($class === null) {
            throw new \RuntimeException('Enum is required');
        }

        $this->classFQCN = $class;
        parent::__construct($marshaller);
    }

    public static function match(\ReflectionNamedType $type): bool
    {
        return $type->getName() === 'enum';
    }

    public function parse($value, $current)
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            $value = $value['name'];
        }

        return ($this->classFQCN)::from($value);
    }

    /**
     * @psalm-suppress UndefinedDocblockClass
     * @return \UnitEnum|null
     */
    public function serialize($value)
    {
        return $value;
    }
}
