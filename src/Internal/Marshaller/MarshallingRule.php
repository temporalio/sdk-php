<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Marshaller;

use Temporal\Internal\Marshaller\Type\TypeInterface;

/**
 * @internal
 */
class MarshallingRule
{
    /**
     * @param string|null $name
     * @param class-string<TypeInterface>|null $type
     * @param self|class-string<TypeInterface>|string|null $of
     */
    public function __construct(
        public ?string $name = null,
        public ?string $type = null,
        public self|string|null $of = null,
    ) {
    }

    public function hasType(): bool
    {
        return $this->type !== null && $this->of !== null;
    }

    /**
     * Generate constructor arguments for the related {@see \Temporal\Internal\Marshaller\Type\Type} object.
     */
    public function getConstructorArgs(): array
    {
        return $this->of === null ? [] : [$this->of];
    }
}
