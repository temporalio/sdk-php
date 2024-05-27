<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\DataConverter;

use Temporal\Api\Common\V1\Payloads;

/**
 * @psalm-import-type TypeEnum from Type
 *
 * @method mixed[] getValues() Returns all values as array.
 */
interface ValuesInterface extends \Countable
{
    /**
     * Checks if any value present.
     *
     * @return bool
     */
    public function isEmpty(): bool;

    /**
     * @param DataConverterInterface $converter
     */
    public function setDataConverter(DataConverterInterface $converter);

    /**
     * Get value by it's index.
     *
     * Returns {@see null} if there are no values and $type has null value
     * like {@see null}, {@see Type::TYPE_VOID} or {@see Type::TYPE_NULL}.
     *
     * @param int $index
     * @param string|\ReflectionClass|\ReflectionType|Type|null $type
     * @return mixed
     */
    public function getValue(int $index, $type);

    /**
     * Returns associated payloads.
     *
     * @return Payloads
     */
    public function toPayloads(): Payloads;
}
