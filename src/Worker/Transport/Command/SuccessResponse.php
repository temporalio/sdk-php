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

    public function __construct(?ValuesInterface $values, string|int $id)
    {
        $this->values = $values ?? EncodedValues::empty();
        parent::__construct(id: $id);
    }

    /**
     * {@inheritDoc}
     */
    public function getPayloads(): ValuesInterface
    {
        return $this->values;
    }
}
