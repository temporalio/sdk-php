<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Marshaller\Meta;

use Spiral\Attributes\NamedArgumentConstructorAttribute;
use Temporal\Internal\Marshaller\Type\NullableType;
use Temporal\Internal\Marshaller\Type\TypeDto;

/**
 * @Annotation
 * @Target({ "PROPERTY" })
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Marshal extends TypeDto implements NamedArgumentConstructorAttribute
{
    /**
     * @param string|null $name
     * @param class-string|null $type
     * @param null|Marshal|string $of
     * @param bool $nullable
     */
    public function __construct(
        ?string $name = null,
        ?string $type = null,
        self|string|null $of = null,
        public bool $nullable = false,
    ) {
        parent::__construct($name, $type, $of);
    }

    /**
     * @return TypeDto
     */
    public function toTypeDto(): TypeDto
    {
        if (!$this->nullable) {
            return $this;
        }

        return new TypeDto(
            $this->name,
            NullableType::class,
            $this->of === null ? $this->type : $this,
        );
    }
}
