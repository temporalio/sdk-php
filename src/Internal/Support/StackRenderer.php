<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Support;

class StackRenderer
{
    /**
     * Sets files and prefixes to be ignored from the stack trace.
     */
    private static array $ignorePaths = [];

    /**
     * @param non-empty-string $path Absolute path to the file or directory to ignore in the stack trace.
     *        Must contain a trailing slash if it's a directory.
     *        Use system {@see DIRECTORY_SEPARATOR} to ensure compatibility across different systems.
     */
    public static function addIgnoredPath(string $path): void
    {
        self::init();
        self::$ignorePaths[] = $path;
    }

    /**
     * Renders trace in easy to digest form, removes references to internal functionality.
     *
     * @param array<array{file?: string, line?: int}> $stackTrace
     * @return array<non-empty-string>
     */
    public static function renderTrace(array $stackTrace): array
    {
        self::init();
        $result = [];
        $internals = 0;

        foreach ($stackTrace as $line) {
            $file = $line['file'] ?? null;
            if ($file === null) {
                continue;
            }

            foreach (self::$ignorePaths as $str) {
                if (\str_starts_with($file, $str)) {
                    ++$internals;
                    continue 2;
                }
            }

            if ($internals > 0) {
                $result === [] or $result[] = "  ... skipped $internals internal frames";
                $internals = 0;
            }

            $result[] = \sprintf(
                '%s:%s',
                $file,
                $line['line'] ?? '-',
            );
        }

        return $result;
    }

    private static function init(): void
    {
        static $run = false;
        if ($run) {
            return;
        }

        $run = true;
        self::$ignorePaths[] = \dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;
    }
}
