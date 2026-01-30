<?php

declare(strict_types=1);

namespace Temporal\Testing\Support;

use Symfony\Component\Console\Style\SymfonyStyle;

class TestOutputStyle extends SymfonyStyle
{
    public function info(array|string $message): void
    {
        $this->block($message, null, 'fg=green', '', false, false);
    }
}
