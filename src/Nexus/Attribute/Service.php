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
 * Marks an interface as a Nexus service.
 *
 * Every method of the annotated interface carrying {@see Operation} declares a separate
 * Nexus operation. By default, the service name is the interface's short name; override it
 * via {@see self::$name} to decouple the wire contract from the PHP class name.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class Service
{
    /**
     * @param string $name Service name as exposed over the wire. Empty means "use the interface short name".
     */
    public function __construct(
        public readonly string $name = '',
    ) {}
}
