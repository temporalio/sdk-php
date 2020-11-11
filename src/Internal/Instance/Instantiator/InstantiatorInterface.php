<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Instance\Instantiator;

use Temporal\Client\Internal\Instance\InstanceInterface;
use Temporal\Client\Internal\Prototype\PrototypeInterface;

/**
 * @psalm-template T of PrototypeInterface
 */
interface InstantiatorInterface
{
    /**
     * @param T $prototype
     * @param class-string $class
     * @return InstanceInterface
     */
    public function instantiate(PrototypeInterface $prototype, string $class): InstanceInterface;
}
