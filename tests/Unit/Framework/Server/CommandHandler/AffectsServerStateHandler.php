<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Framework\Server\CommandHandler;

use Carbon\Carbon;
use Temporal\Worker\Transport\Command\CommandInterface;

interface AffectsServerStateHandler
{
    public function updateState(CommandInterface $command, Carbon $state): void;
}
