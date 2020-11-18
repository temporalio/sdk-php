<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Marshaller\Meta;

use JetBrains\PhpStorm\ExpectedValues;

/**
 * @psalm-type ExportScope = Scope::PROPERTY_*
 *
 * @Annotation
 * @Target({ "CLASS" })
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Scope
{
    /**
     * @var int
     */
    public const PROPERTY_PRIVATE = \ReflectionProperty::IS_PRIVATE;

    /**
     * @var int
     */
    public const PROPERTY_PROTECTED = \ReflectionProperty::IS_PROTECTED;

    /**
     * @var int
     */
    public const PROPERTY_PUBLIC = \ReflectionProperty::IS_PUBLIC;

    /**
     * @var int
     */
    public const PROPERTY_ALL = self::PROPERTY_PRIVATE
                              | self::PROPERTY_PROTECTED
                              | self::PROPERTY_PUBLIC
    ;

    /**
     * @var ExportScope
     */
    #[ExpectedValues(Scope::class)]
    public int $properties = self::PROPERTY_PUBLIC;

    /**
     * @var bool
     */
    public bool $copyOnWrite = false;
}
