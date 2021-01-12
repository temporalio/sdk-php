<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\DataConverter;

use Temporal\Api\Common\V1\Payloads;

interface ValuesInterface
{
    /**
     * Checks if any value present.
     *
     * @return bool
     */
    public function isEmpty(): bool;

    /**
     * @param DataConverterInterface $dataConverter
     */
    public function setDataConverter(DataConverterInterface $dataConverter);

    /**
     * Get value by it's index.
     *
     * @param int $index
     * @param $type
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
