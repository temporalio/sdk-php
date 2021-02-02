<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Workflow;

use Temporal\Internal\Declaration\Prototype\Prototype;

abstract class Proxy
{
    /**
     * @param string $method
     * @param array $args
     */
    abstract public function __call(string $method, array $args);

    /**
     * @psalm-template T of Prototype
     *
     * @param array<T> $prototypes
     * @param string $name
     * @return T|null
     */
    protected function findPrototypeByHandlerName(array $prototypes, string $name): ?Prototype
    {
        foreach ($prototypes as $prototype) {
            $handler = $prototype->getHandler();

            if ($handler->getName() === $name) {
                return $prototype;
            }
        }

        return null;
    }
}
