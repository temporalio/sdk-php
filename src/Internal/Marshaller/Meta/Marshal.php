<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Marshaller\Meta;

use Spiral\Attributes\NamedArgumentConstructor;
use Temporal\Internal\Marshaller\MarshallingRule;
use Temporal\Internal\Marshaller\Type\NullableType;

/**
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({ "PROPERTY" })
 */
#[\Attribute(\Attribute::TARGET_PROPERTY), NamedArgumentConstructor]
class Marshal extends MarshallingRule
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
     * @return MarshallingRule
     */
    public function toTypeDto(): MarshallingRule
    {
        if (!$this->nullable) {
            return $this;
        }

        return new MarshallingRule(
            $this->name,
            NullableType::class,
            $this->of === null ? $this->type : $this,
        );
    }
}
