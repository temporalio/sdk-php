<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App;

final class Support
{
    public static function echoException(\Throwable $e): void
    {
        $trace = \array_filter($e->getTrace(), static fn(array $trace): bool =>
            isset($trace['file']) &&
            !\str_contains($trace['file'], DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR),
        );
        if ($trace !== []) {
            $line = \reset($trace);
            echo "-> \e[1;33m{$line['file']}:{$line['line']}\e[0m\n";
        }

        do {
            /** @var \Throwable $err */
            $name = \ltrim(\strrchr($e::class, "\\") ?: $e::class, "\\");
            echo "\e[1;34m$name\e[0m\n";
            echo "\e[3m{$e->getMessage()}\e[0m\n";
            $e = $e->getPrevious();
        } while ($e !== null);
    }
}
