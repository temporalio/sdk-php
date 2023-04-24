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
use Temporal\Internal\Marshaller\Type\ArrayType;

/**
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({ "PROPERTY", "METHOD" })
 */
#[\Attribute(\Attribute::TARGET_PROPERTY), NamedArgumentConstructor]
final class MarshalArray extends Marshal
{
    /**
     * @param string|null $name
     * @param null|string $of
     * @param bool $nullable
     */
    public function __construct(
        string $name = null,
        string $of = null,
        bool $nullable = true,
    ) {
        parent::__construct($name, ArrayType::class, $of, $nullable);
    }
}
