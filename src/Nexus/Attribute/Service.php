<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus\Attribute;

/**
 * Marks a class or interface as a Nexus service.
 *
 * The annotated type is the service contract: every method carrying {@see Operation} or
 * {@see AsyncOperation} declares a separate Nexus operation. The attribute is accepted on
 * either a `#[Service]`-annotated interface (with a separate impl class implementing it)
 * or directly on the implementation class — when applied to a class, that class is its own
 * contract.
 *
 * By default, the service name is the type's short name; override it via {@see self::$name}
 * to decouple the wire contract from the PHP class name.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class Service
{
    /**
     * @param string $name Service name as exposed over the wire. Empty means "use the contract's short name".
     */
    public function __construct(
        public readonly string $name = '',
    ) {}
}
