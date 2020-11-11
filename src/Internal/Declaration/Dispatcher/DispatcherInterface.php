<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Declaration\Dispatcher;

interface DispatcherInterface
{
    /**
     * @param object|null $ctx
     * @param array $arguments
     * @return mixed
     */
    public function dispatch(?object $ctx, array $arguments);
}
