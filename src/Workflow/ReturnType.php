<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Workflow;

use JetBrains\PhpStorm\ExpectedValues;
use JetBrains\PhpStorm\Immutable;
use Spiral\Attributes\NamedArgumentConstructor;
use Temporal\DataConverter\Type;

/**
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({ "METHOD" })
 */
#[\Attribute(\Attribute::TARGET_METHOD), NamedArgumentConstructor]
final class ReturnType
{
    public const TYPE_ANY    = Type::TYPE_ANY;
    public const TYPE_STRING = Type::TYPE_STRING;
    public const TYPE_BOOL   = Type::TYPE_BOOL;
    public const TYPE_INT    = Type::TYPE_INT;
    public const TYPE_FLOAT  = Type::TYPE_FLOAT;

    /**
     * @var string
     */
    #[Immutable]
    public string $name;

    /**
     * @var bool
     */
    #[Immutable]
    public bool $nullable;

    /**
     * @param string $name
     * @param bool $nullable
     */
    public function __construct(
        #[ExpectedValues(valuesFromClass: Type::class)]
        string $name,
        bool $nullable = false
    ) {
        $this->name = $name;
        $this->nullable = $nullable;
    }
}
