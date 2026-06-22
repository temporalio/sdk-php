<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\DataConverter;

/**
 * Opt-in contract for a converter or codec that can be bound to a {@see SerializationContext}.
 *
 * Implementations must keep a fully functional context-free path; {@see self::withSerializationContext()}
 * is called often and must return a cheap, immutable copy rather than rebuild expensive state.
 */
interface SerializationContextAwareInterface
{
    public function withSerializationContext(?SerializationContext $context): static;
}
