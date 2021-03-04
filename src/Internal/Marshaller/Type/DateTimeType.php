<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Marshaller\Type;

use JetBrains\PhpStorm\Pure;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Support\DateTime;
use Temporal\Internal\Support\Inheritance;

class DateTimeType extends Type implements DetectableTypeInterface
{
    /**
     * @var string
     */
    private string $format;

    /**
     * @param MarshallerInterface $marshaller
     * @param string $format
     */
    #[Pure]
    public function __construct(MarshallerInterface $marshaller, string $format = \DateTimeInterface::RFC3339)
    {
        $this->format = $format;

        parent::__construct($marshaller);
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
    public function parse($value, $current): \DateTimeInterface
    {
        return DateTime::parse($value);
    }

    /**
     * {@inheritDoc}
     */
    public function serialize($value): string
    {
        return DateTime::parse($value)
            ->format($this->format);
    }
}
