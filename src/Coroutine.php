<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client;

final class Coroutine
{
    /**
     * @var string
     */
    private const ERROR_INVALID_ARGUMENT = 'Argument of %s accepts only \Generator arguments, but %s given';

    /**
     * @param \Generator[] $coroutines
     * @return \Generator
     */
    public static function cooperative(iterable $coroutines): \Generator
    {
        /** @var \Generator[] $coroutines */
        $coroutines = [...$coroutines];
        $result = [];

        while (\count($coroutines)) {
            $promises = [];

            foreach ($coroutines as $index => $generator) {
                if (! $generator instanceof \Generator) {
                    throw new \InvalidArgumentException(\vsprintf(self::ERROR_INVALID_ARGUMENT, [
                        __METHOD__,
                        \get_debug_type($generator),
                    ]));
                }

                if (! $generator->valid()) {
                    unset($coroutines[$index]);
                    $result[$index] = $generator->getReturn();
                    continue;
                }

                $promises[$index] = $generator->current();
            }

            foreach (yield Promise::all($promises) as $index => $current) {
                $coroutines[$index]->send($current);
            }
        }

        \ksort($result);

        return $result;
    }
}
