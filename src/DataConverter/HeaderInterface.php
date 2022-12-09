<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\DataConverter;

use IteratorAggregate;
use Temporal\Api\Common\V1\Header;

/**
 * @psalm-type TKey=array-key
 * @psalm-type TValue=string
 * @extends IteratorAggregate<TKey, string>
 * @psalm-immutable
 */
interface HeaderInterface extends \Countable, IteratorAggregate
{
    /**
     * Checks if any value present.
     */
    public function isEmpty(): bool;

    /**
     * @param TKey $index
     */
    public function getValue(int|string $index): ?string;

    /**
     * @param TKey $key
     * @param TValue $value
     */
    public function withValue(int|string $key, string $value): self;

    public function toHeader(): Header;
}
