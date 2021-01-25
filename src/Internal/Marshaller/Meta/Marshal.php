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
use Temporal\Internal\Marshaller\Type\TypeInterface;

/**
 * @Annotation
 * @Target({ "PROPERTY" })
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Marshal implements NamedArgumentConstructorAttribute
{
    /**
     * @var string|null
     */
    public ?string $name = null;

    /**
     * @var class-string<TypeInterface>|null
     */
    public ?string $type = null;

    /**
     * @var class-string<TypeInterface>|string|null
     */
    public ?string $of = null;

    /**
     * @param string|null $name
     * @param class-string<TypeInterface>|null $type
     * @param class-string<TypeInterface>|string|null $of
     */
    public function __construct(
        string $name = null,
        string $type = null,
        string $of = null
    ) {
        $this->name = $name;
        $this->type = $type;
        $this->of = $of;
    }
}
