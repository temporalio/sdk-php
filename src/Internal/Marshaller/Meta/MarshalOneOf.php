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
use Temporal\Internal\Marshaller\Type\OneOfType;

#[\Attribute(\Attribute::TARGET_PROPERTY), NamedArgumentConstructor]
final class MarshalOneOf extends Marshal
{
    /**
     * @param non-empty-array<non-empty-string, class-string> $cases
     * @param non-empty-string|null $name
     * @param class-string|null $of
     * @param bool $nullable
     */
    public function __construct(
        private array $cases,
        string $name = null,
        ?string $of = null,
        bool $nullable = true,
    ) {
        parent::__construct($name, OneOfType::class, $of, $nullable);
    }

    public function getConstructorArgs(): array
    {
        return [$this->of, $this->cases, $this->nullable];
    }
}
