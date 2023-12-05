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
 * You may use this annotation multiple times to specify multiple marshalling rules for a single property. It may be
 * useful when there are multiple ways to unmarshal a property (e.g. multiple names for a single property: `DateList`
 * or `date_list`). In this case, the first rule has the highest priority and will be used in marshalling
 * (serialization) process.
 *
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({ "PROPERTY", "METHOD" })
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::IS_REPEATABLE), NamedArgumentConstructor]
final class MarshalArray extends Marshal
{
    /**
     * @param string|null $name
     * @param Marshal|string|null $of
     * @param bool $nullable
     */
    public function __construct(
        string $name = null,
        Marshal|string|null $of = null,
        bool $nullable = true,
    ) {
        parent::__construct($name, ArrayType::class, $of, $nullable);
    }
}
