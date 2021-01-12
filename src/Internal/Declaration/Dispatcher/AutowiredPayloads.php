<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration\Dispatcher;

use Temporal\DataConverter\ValuesInterface;

/**
 * @psalm-type FunctionExecutor = \Closure(object|null, array): mixed
 */
class AutowiredPayloads extends Dispatcher
{
    /**
     * @param object|null $ctx
     * @param array $arguments
     * @return mixed
     */
    public function dispatchValues(?object $ctx, ValuesInterface $values)
    {
        $arguments = [];
        for ($i = 0; $i < $values->count(); $i++) {
            $arguments[] = $values->getValue($i, $this->getArgumentTypes()[$i] ?? null);
        }

        return parent::dispatch($ctx, $arguments);
    }
}
