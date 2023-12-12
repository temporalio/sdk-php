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
     * @param int $index
     * @param Type|TypeEnum|mixed $type
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
