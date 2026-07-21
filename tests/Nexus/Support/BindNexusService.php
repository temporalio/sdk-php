<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Support;

use Spiral\Attributes\AttributeReader;
use Temporal\Internal\Declaration\Instantiator\NexusServiceInstantiator;
use Temporal\Internal\Declaration\NexusServiceInstance;
use Temporal\Internal\Declaration\Reader\NexusServiceReader;

/**
 * Test helper: wires Reader + Instantiator the same way Worker does, returning
 * a {@see NexusServiceInstance} bound to the given impl object.
 */
trait BindNexusService
{
    protected static function bindNexusService(object $instance): NexusServiceInstance
    {
        $reader = new NexusServiceReader(new AttributeReader());
        $prototype = $reader->fromClass(\get_class($instance))->withInstance($instance);
        return (new NexusServiceInstantiator())->instantiate($prototype);
    }
}
