<?php

/**
 * This file is part of Nexus RPC SDK for PHP package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus\Attribute;

/**
 * Marks a class as a concrete implementation of a Nexus {@see Service}.
 *
 * The annotated class must contain exactly one {@see OperationImpl}-annotated method per
 * operation declared on the referenced service interface.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class ServiceImpl
{
    /**
     * @param class-string $service The {@see Service}-annotated interface this class implements.
     */
    public function __construct(
        public readonly string $service,
    ) {}
}
