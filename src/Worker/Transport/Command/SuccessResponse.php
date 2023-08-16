<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Transport\Command;

use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\ValuesInterface;

class SuccessResponse extends Response implements SuccessResponseInterface
{
    protected ValuesInterface $values;

    /**
     * @param int<0, max> $historyLength
     */
    public function __construct(?ValuesInterface $values, string|int $id, int $historyLength = 0)
    {
        $this->values = $values ?? EncodedValues::empty();
        parent::__construct(id: $id, historyLength: $historyLength);
    }

    public function getPayloads(): ValuesInterface
    {
        return $this->values;
    }
}
