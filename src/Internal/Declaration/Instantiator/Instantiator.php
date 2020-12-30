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
     * @param PrototypeInterface $prototype
     * @return \ReflectionClass|null
     */
    protected function getClass(PrototypeInterface $prototype): ?\ReflectionClass
    {
        return $prototype->getClass();
    }

    /**
     * @param PrototypeInterface $prototype
     * @return object|null
     * @throws \ReflectionException
     */
    protected function getInstance(PrototypeInterface $prototype): ?object
    {
        if ($class = $this->getClass($prototype)) {
            return $class->newInstance();
        }

        return null;
    }
}
