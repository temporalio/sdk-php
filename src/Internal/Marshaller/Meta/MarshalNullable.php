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
use Temporal\Internal\Marshaller\MarshallingRule;
use Temporal\Internal\Marshaller\Type\NullableType;

/**
 * @internal
 */
#[\Attribute(\Attribute::TARGET_PROPERTY), NamedArgumentConstructor]
final class MarshalNullable extends Marshal
{
    /**
     * @param non-empty-string|null $name
     */
    public function __construct(
        string $name = null,
        string|MarshallingRule|null $rule = null,
    ) {
        parent::__construct($name, NullableType::class, $rule);
    }

    public function hasType(): bool
    {
        return match (true) {
            $this->of === null => false,
            $this->of instanceof MarshallingRule => $this->of->hasType(),
            default => true,
        };
    }
}
