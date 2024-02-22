<?php

declare(strict_types=1);

namespace Temporal\Workflow\Update;

use Temporal\DataConverter\ValuesInterface;

final class UpdateResult
{
    public const COMMAND_VALIDATED = 'UpdateValidated';
    public const COMMAND_COMPLETED = 'UpdateCompleted ';

    public function __construct(
        public readonly string $command,
        public readonly ?ValuesInterface $result = null,
        public readonly ?\Throwable $failure = null,
    ) {
    }
}
