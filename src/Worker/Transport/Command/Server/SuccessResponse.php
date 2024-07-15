<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Transport\Command\Server;

use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Worker\Transport\Command\SuccessResponseInterface;

class SuccessResponse extends ServerResponse implements SuccessResponseInterface
{
    protected ValuesInterface $values;

    public function __construct(?ValuesInterface $values, string|int $id, TickInfo $info)
    {
        $this->values = $values ?? EncodedValues::empty();
        parent::__construct(id: $id, info: $info);
    }

    public function getPayloads(): ValuesInterface
    {
        return $this->values;
    }
}
