<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration\Instantiator;

use Temporal\Internal\Declaration\Prototype\PrototypeInterface;

abstract class Instantiator implements InstantiatorInterface
{
    /**
     * @throws \ReflectionException
     */
    protected function getInstance(PrototypeInterface $prototype): object
    {
        return $prototype->getClass()->newInstance();
    }
}
