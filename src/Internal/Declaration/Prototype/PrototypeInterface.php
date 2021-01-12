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
     *
     * @return string
     */
    public function getID(): string;

    /**
     * Returns a link to the class within which the handler is defined.
     *
     * @return \ReflectionClass|null
     */
    public function getClass(): ?\ReflectionClass;

    /**
     * Returns information that the class is defined with an interface
     * attribute and can be used as a stub class.
     *
     * @return bool
     */
    public function isInterfaced(): bool;

    /**
     * Returns the reflection of the handler function.
     *
     * @return \ReflectionFunctionAbstract
     */
    public function getHandler(): \ReflectionFunctionAbstract;
}
