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
use Temporal\Exception\InvalidArgumentException;

/**
 * @psalm-type FunctionExecutor = \Closure(object|null, array): mixed
 * @internal
 */
class AutowiredPayloads extends Dispatcher
{
    public function dispatch(object $ctx, array $arguments): mixed
    {
        try {
            return parent::dispatch($ctx, $arguments);
        } catch (\TypeError $e) {
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function resolveArguments(ValuesInterface $values): array
    {
        $arguments = [];
        try {
            for ($i = 0, $count = $values->count(); $i < $count; $i++) {
                $arguments[] = $values->getValue($i, $this->getArgumentTypes()[$i] ?? null);
            }
        } catch (\Throwable $e) {
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }
        return $arguments;
    }
}
