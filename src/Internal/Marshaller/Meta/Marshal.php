<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Marshaller\Meta;

use Temporal\Client\Internal\Marshaller\Type\TypeInterface;

/**
 * @Annotation
 * @Target({ "PROPERTY" })
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Marshal
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
     * @var mixed
     */
    public $of;
}
