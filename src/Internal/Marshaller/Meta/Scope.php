<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Marshaller\Meta;

use JetBrains\PhpStorm\ExpectedValues;
use Spiral\Attributes\NamedArgumentConstructor;

/**
 * @psalm-type ExportScope = Scope::VISIBILITY_*
 *
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({ "CLASS" })
 */
#[\Attribute(\Attribute::TARGET_CLASS), NamedArgumentConstructor]
class Scope
{
    /**
     * @var int
     */
    public const VISIBILITY_PRIVATE = \ReflectionProperty::IS_PRIVATE;

    /**
     * @var int
     */
    public const VISIBILITY_PROTECTED = \ReflectionProperty::IS_PROTECTED;

    /**
     * @var int
     */
    public const VISIBILITY_PUBLIC = \ReflectionProperty::IS_PUBLIC;

    /**
     * @var int
     */
    public const VISIBILITY_ALL = self::VISIBILITY_PRIVATE
                                | self::VISIBILITY_PROTECTED
                                | self::VISIBILITY_PUBLIC;

    /**
     * @var ExportScope
     */
    #[ExpectedValues(valuesFromClass: Scope::class)]
    public int $properties;

    /**
     * @var bool
     */
    public bool $copyOnWrite;

    /**
     * @param ExportScope $properties
     * @param bool $copyOnWrite
     */
    public function __construct(
        int $properties = self::VISIBILITY_PUBLIC,
        bool $copyOnWrite = false
    ) {
        $this->properties = $properties;
        $this->copyOnWrite = $copyOnWrite;
    }
}
