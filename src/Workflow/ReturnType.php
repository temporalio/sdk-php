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
use Spiral\Attributes\NamedArgumentConstructorAttribute;
use Temporal\DataConverter\Type;

/**
 * @Annotation
 * @Target({ "METHOD" })
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class ReturnType implements NamedArgumentConstructorAttribute
{
    /**
     * @var string
     */
    #[Immutable]
    public string $name;

    /**
     * @param string $name
     */
    public function __construct(
        #[ExpectedValues(valuesFromClass: Type::class)]
        string $name
    ) {
        $this->name = $name;
    }
}
