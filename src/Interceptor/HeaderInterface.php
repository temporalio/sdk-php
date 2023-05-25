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
use Temporal\Api\Common\V1\Header;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\Type;

/**
 * @psalm-type TKey=array-key
 * @psalm-type TValue=string
 * @psalm-import-type TypeEnum from Type
 *
 * @extends IteratorAggregate<TKey, string>
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
    public function withValue(int|string $key, string $value): self;

    /**
     * Make a protobuf Header message.
     *
     * @return Header
     */
    public function toHeader(): Header;

    /**
     * @param DataConverterInterface $converter
     */
    public function setDataConverter(DataConverterInterface $converter);
}
