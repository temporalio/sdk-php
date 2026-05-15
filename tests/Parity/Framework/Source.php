<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\Framework;

/**
 * Identifies which Temporal SDK produced a captured event-history JSON.
 * Used by the normalizer registry to pick an SDK-specific event normalizer.
 */
enum Source: string
{
    case PHP = 'php';
    case JAVA = 'java';
    case GO = 'go';
    case TYPESCRIPT = 'typescript';

    /**
     * Best-effort SDK detection from a `sdkMetadata.sdkName` string captured
     * in `WorkflowTaskCompleted` events. Caller is expected to know the
     * source already; this is a fallback for fixtures with mixed origin.
     */
    public static function fromSdkName(string $sdkName): self
    {
        return match (true) {
            \str_contains($sdkName, 'java') => self::JAVA,
            \str_contains($sdkName, 'typescript') => self::TYPESCRIPT,
            \str_contains($sdkName, 'go') => self::GO,
            \str_contains($sdkName, 'php') => self::PHP,
            default => throw new \InvalidArgumentException(
                "Unrecognised sdkName for Source detection: \"{$sdkName}\"",
            ),
        };
    }

    /**
     * Heuristic: PHP RoadRunner workers identify themselves as
     * `roadrunner:<task-queue>:<uuid>`. Java/Go workers use `<pid>@<host>`.
     * Returns null when the identity is ambiguous (worker uses a generic
     * `<pid>@<host>` pattern shared by multiple SDKs).
     */
    public static function fromIdentity(string $identity): ?self
    {
        if (\str_starts_with($identity, 'roadrunner:')) {
            return self::PHP;
        }
        return null;
    }
}
