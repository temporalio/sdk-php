<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Marshaller\Meta;

use DateTimeInterface;
use Google\Protobuf\Timestamp;
use Spiral\Attributes\NamedArgumentConstructor;
use Temporal\Internal\Marshaller\Type\DateTimeType;

#[\Attribute(\Attribute::TARGET_PROPERTY), NamedArgumentConstructor]
final class MarshalDateTime extends Marshal
{
    /**
     * @param non-empty-string|null $name
     * @param class-string<DateTimeInterface>|null $of Local representation of the date.
     *        May be any of internal or Carbon {@see DatetimeInterface} implementations.
     * @param non-empty-string $to Datetime format or {@see Timestamp} class name.
     * @param bool $nullable
     */
    public function __construct(
        string $name = null,
        ?string $of = null,
        private string $to = \DateTimeInterface::RFC3339,
        bool $nullable = true,
    ) {
        parent::__construct($name, DateTimeType::class, $of, $nullable);
    }

    public function getConstructorArgs(): array
    {
        return [$this->of, $this->to];
    }
}
