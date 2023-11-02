<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Interceptor;

use IteratorAggregate;
use Temporal\DataConverter\Type;

/**
 * @psalm-type TKey=array-key
 * @psalm-type TValue=mixed
 * @psalm-import-type TypeEnum from Type
 *
 * @extends IteratorAggregate<TKey, TValue>
 */
interface HeaderInterface extends \Countable, IteratorAggregate
{
    /**
     * Checks if any value present.
     */
    public function isEmpty(): bool;

    /**
     * @param TKey $index
     * @param Type|TypeEnum|mixed $type
     *
     * @return mixed Returns {@see null} if value not found.
     */
    public function getValue(int|string $index, mixed $type = null): mixed;

    /**
     * @param TKey $key
     * @param TValue $value
     *
     * @psalm-mutation-free
     */
    public function withValue(int|string $key, mixed $value): self;
}
