<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Support;

use Temporal\Api\Sdk\V1\EnhancedStackTrace;
use Temporal\Api\Sdk\V1\StackTrace;
use Temporal\Api\Sdk\V1\StackTraceFileLocation;
use Temporal\Api\Sdk\V1\StackTraceFileSlice;
use Temporal\Api\Sdk\V1\StackTraceSDKInfo;

/**
 * @internal
 */
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
     * @param array<array{
     *      file?: string,
     *      line?: int<0, max>,
     *      function?: non-empty-string,
     *      class?: class-string,
     *      type?: non-empty-string
     *  }> $stackTrace
     */
    public static function renderString(array $stackTrace): string
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

        return \implode("\n", $result);
    }

    /**
     * @param array<array{
     *     file?: string,
     *     line?: int<0, max>,
     *     function?: non-empty-string,
     *     class?: class-string,
     *     type?: non-empty-string
     * }> $stackTrace
     */
    public static function renderProto(array $stackTrace): EnhancedStackTrace
    {
        self::init();
        $sdk = (new StackTraceSDKInfo())
            // ->setName()
            // ->setVersion()
        ;

        /** @var array<non-empty-string, StackTraceFileSlice> $sources */
        $sources = [];
        /** @var list<StackTrace> $stacks */
        $stacks = [];

        /** @var list<StackTraceFileLocation> $locations */
        $locations = [];

        foreach ($stackTrace as $line) {
            $location = (new StackTraceFileLocation());

            $isInternal = false;
            $file = $line['file'] ?? null;
            if ($file !== null) {
                $location->setFilePath($file);
                foreach (self::$ignorePaths as $str) {
                    if (\str_starts_with($file, $str)) {
                        $isInternal = true;
                        break;
                    }
                }
            }

            isset($line['line']) and $location->setLine($line['line']);

            if (isset($line['function'])) {
                $location->setFunctionName(\sprintf(
                    '%s%s%s',
                    ($line['class'] ?? ''),
                    ($line['type'] ?? ''),
                    $line['function'],
                ));
            }

            $locations[] = $location->setInternalCode($isInternal);

            // Store source code for non-internal files
            if (!$isInternal && $file !== null && !\array_key_exists($file, $sources)) {
                try {
                    $code = @\file_get_contents($file);
                } catch (\Throwable $e) {
                    $code = \sprintf("Cannot access code.\n---\n%s", $e->getMessage());
                }

                $sources[$file] = (new StackTraceFileSlice())
                    ->setLineOffset(0)
                    ->setContent($code === false ? "Failed to read file." : $code);
            }
        }
        $stacks[] = (new StackTrace())
            ->setLocations($locations);

        return (new EnhancedStackTrace())
            ->setSdk($sdk)
            ->setSources($sources)
            ->setStacks($stacks);
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
