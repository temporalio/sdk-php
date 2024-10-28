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
use Temporal\Internal\Marshaller\Type\AssocArrayType;

/**
 * The same as {@see MarshalArray}, but it forces the marshaller to treat the property as an associative array.
 * Useful when you send an empty array, but the recipient can't handle it because expects an object,
 * but PHP's {@see json_encode()} encodes an empty array as a list `[]` instead of an object `{}`.
 *
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({ "PROPERTY", "METHOD" })
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::IS_REPEATABLE), NamedArgumentConstructor]
final class MarshalAssocArray extends Marshal
{
    public function __construct(
        string $name = null,
        Marshal|string|null $of = null,
        bool $nullable = true,
    ) {
        parent::__construct($name, AssocArrayType::class, $of, $nullable);
    }
}
