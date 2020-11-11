<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Instance;

/**
 * @template-covariant MetadataAttribute of object
 * @template-covariant MethodAttribute of object
 *
 * @psalm-type DispatchableHandler = callable(array): mixed
 */
interface InstanceInterface
{
    /**
     * @return object
     */
    public function getContext(): object;

    /**
     * @psalm-return MethodAttribute
     *
     * @return object
     */
    public function getMethod(): object;

    /**
     * @psalm-return DispatchableHandler
     *
     * @return callable
     */
    public function getHandler(): callable;

    /**
     * @psalm-return MetadataAttribute
     *
     * @return object
     */
    public function getMetadata(): object;
}
