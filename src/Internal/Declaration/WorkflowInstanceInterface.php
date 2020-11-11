<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Declaration;

/**
 * @psalm-import-type DispatchableHandler from InstanceInterface
 */
interface WorkflowInstanceInterface extends InstanceInterface
{
    /**
     * @param string $name
     * @return \Closure|null
     */
    public function findQueryHandler(string $name): ?\Closure;

    /**
     * @param string $name
     * @return \Closure|null
     */
    public function findSignalHandler(string $name): ?\Closure;
}
