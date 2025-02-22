<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration\Prototype;

use Temporal\Internal\Repository\Identifiable;

interface PrototypeInterface extends Identifiable
{
    /**
     * Returns the name of the main handler.
     */
    public function getID(): string;

    /**
     * Returns a link to the class within which the handler is defined.
     */
    public function getClass(): \ReflectionClass;

    /**
     * Returns the reflection of the handler function.
     */
    public function getHandler(): ?\ReflectionMethod;
}
