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

class UpdateResponse extends Response
{
    private readonly ValuesInterface $values;

    public function __construct(
        private readonly string $status,
        ?ValuesInterface $values,
        private readonly ?\Throwable $failure,
        string|int $id,
        private readonly array $options = [],
    ) {
        $this->values = $values ?? EncodedValues::empty();
        parent::__construct(id: $id, historyLength: 0);
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getPayloads(): ValuesInterface
    {
        return $this->values;
    }

    public function getFailure(): ?\Throwable
    {
        return $this->failure;
    }

    public function getOptions(): array
    {
        return $this->options;
    }
}
