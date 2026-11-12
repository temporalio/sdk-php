<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App;

use Temporal\Api\History\V1\HistoryEvent;
use Temporal\Api\History\V1\MarkerRecordedEventAttributes;
use Temporal\Worker\Core\ActivityTasks;

final class Support
{
    public static function isSideEffectMarker(HistoryEvent $event): bool
    {
        $marker = $event->getMarkerRecordedEventAttributes();

        return match ($marker?->getMarkerName()) {
            'SideEffect' => true,
            'core_local_activity' => self::coreLocalActivityType($marker) === ActivityTasks::SIDE_EFFECT,
            default => false,
        };
    }

    private static function coreLocalActivityType(MarkerRecordedEventAttributes $marker): string
    {
        $data = $marker->getDetails()['data'] ?? null;
        $payload = $data?->getPayloads()[0] ?? null;
        $decoded = $payload === null ? null : \json_decode($payload->getData(), true);

        return \is_array($decoded) ? (string) ($decoded['activity_type'] ?? '') : '';
    }

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
