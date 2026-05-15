<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\Framework;

use Temporal\Worker\Logger\StderrLogger;

final class HistoryLoader
{
    public static function requireExists(string $path): void
    {
        if (\is_file($path)) {
            return;
        }
        throw new \RuntimeException(
            "Parity fixture not found: {$path}. "
            . 'Capture it first: `make -C tests/Parity build && make -C tests/Parity fixtures`.',
        );
    }

    public static function loadJson(string $path, Source $source): EventHistory
    {
        self::requireExists($path);

        $raw = (string) \file_get_contents($path);
        if ($raw === '') {
            throw new \RuntimeException("Parity fixture is empty: {$path}");
        }

        try {
            $payload = \json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException(
                "Parity fixture is not valid JSON: {$path} ({$e->getMessage()})",
                previous: $e,
            );
        }

        if (!\is_array($payload)) {
            throw new \RuntimeException("Parity fixture root is not an object: {$path}");
        }

        $events = $payload['events'] ?? null;
        if (!\is_array($events)) {
            throw new \RuntimeException("Parity fixture has no `events` array: {$path}");
        }

        foreach ($events as $index => $event) {
            if (!\is_array($event)) {
                throw new \RuntimeException(
                    "Parity fixture event #{$index} is not an object in {$path}",
                );
            }
        }

        if (\getenv('PARITY_DEBUG') === '1') {
            (new StderrLogger())->debug('parity fixture loaded', [
                'source' => $source->value,
                'events' => \count($events),
                'path' => $path,
            ]);
        }

        return new EventHistory($source, \array_values($events), $payload);
    }
}
