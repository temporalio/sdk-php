<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Transport\Command\Client;

use Temporal\DataConverter\ValuesInterface;
use Temporal\Worker\Transport\Command\SuccessResponseInterface;

final class SuccessClientResponse implements SuccessResponseInterface
{
    public function __construct(
        private readonly int|string $id,
        private readonly ?ValuesInterface $values = null,
    ) {}

    public function getID(): string|int
    {
        return $this->id;
    }

    public function getPayloads(): ValuesInterface
    {
        return $this->values;
    }
}
