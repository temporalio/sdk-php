<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Declaration\Dispatcher;

/**
 * @psalm-type FunctionExecutor = \Closure(object|null, array): mixed
 */
class Autowired extends Dispatcher
{
    /**
     * @param array $arguments
     * @return array
     */
    public function resolve(array $arguments): array
    {
        return [$arguments[0]];
    }

    /**
     * @param object|null $ctx
     * @param array $arguments
     * @return mixed
     */
    public function dispatch(?object $ctx, array $arguments)
    {
        return parent::dispatch($ctx, $this->resolve($arguments));
    }
}
