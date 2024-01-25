<?php

declare(strict_types=1);

namespace Temporal\Workflow\Update;

use Temporal\DataConverter\ValuesInterface;

final class UpdateResult
{
    public function __construct(
        public readonly string $status,
        public readonly ?ValuesInterface $result = null,
        public readonly ?\Throwable $error = null,
        public readonly array $options = [],
    ) {
    }
}
