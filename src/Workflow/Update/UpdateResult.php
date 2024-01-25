<?php

declare(strict_types=1);

namespace Temporal\Workflow\Update;

use Temporal\DataConverter\ValuesInterface;

final class UpdateResult
{
    public const ACCEPT = 'accept';
    public const REJECT = 'reject';

    public const COMPLETE = 'complete';
    public const ERROR_COMPLETE = 'ecomplete';

    public function __construct(
        public readonly string $status,
        public readonly ?ValuesInterface $result = null,
        public readonly ?\Throwable $error = null,
        public readonly array $options = [],
    ) {
    }
}
