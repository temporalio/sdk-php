<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus\Serializer;

/**
 * Serializer used to convert values to bytes and vice-versa.
 *
 * Sentinel types `void`, `mixed`, `null` are produced by
 * {@see \Temporal\Nexus\OperationDefinition::fromMethod()} when the operation slot
 * has no concrete type. Implementations must handle them gracefully
 * (typically: return `null` from `deserialize`, accept `null` in `serialize`)
 * instead of treating them as class names.
 */
interface SerializerInterface
{
    public function serialize(mixed $value): Content;

    public function deserialize(Content $content, string $type): mixed;
}
