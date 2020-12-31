<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration;

use Temporal\DataConverter\DataConverterInterface;

/**
 * @psalm-import-type DispatchableHandler from InstanceInterface
 */
interface WorkflowInstanceInterface extends InstanceInterface
{
    /**
     * @return DataConverterInterface
     */
    public function getDataConverter(): DataConverterInterface;

    /**
     * @param string $name
     * @return \Closure|null
     */
    public function findQueryHandler(string $name): ?\Closure;

    /**
     * @param string $name
     * @param callable $handler
     */
    public function addQueryHandler(string $name, callable $handler): void;

    /**
     * @param string $name
     * @return \Closure
     */
    public function getSignalHandler(string $name): \Closure;

    /**
     * @param string $name
     * @param callable $handler
     */
    public function addSignalHandler(string $name, callable $handler): void;
}
