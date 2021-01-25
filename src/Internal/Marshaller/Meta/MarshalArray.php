<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Marshaller\Meta;

use Temporal\Internal\Marshaller\Type\ArrayType;
use Temporal\Internal\Marshaller\Type\TypeInterface;

/**
 * @Annotation
 * @Target({ "PROPERTY", "METHOD" })
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class MarshalArray extends Marshal
{
    /**
     * @param string|null $name
     * @param class-string<TypeInterface>|string|null $of
     */
    public function __construct(string $name = null, string $of = null)
    {
        parent::__construct($name, ArrayType::class, $of);
    }
}
