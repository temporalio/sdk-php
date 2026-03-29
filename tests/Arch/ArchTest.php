<?php

declare(strict_types=1);

namespace Temporal\Tests\Arch;

use PHPUnit\Framework\TestCase;

final class ArchTest extends TestCase
{
    public function testForgottenDebugFunctions(): void
    {
        $root = \dirname(__DIR__, 2);
        $functions = ['dump', 'trap', 'tr', 'td', 'var_dump'];

        foreach ($this->phpFiles($root) as $file) {
            $match = $this->findForbiddenFunctionCall(\file_get_contents($file), $functions);
            if ($match === null) {
                continue;
            }

            [$function, $line] = $match;

            self::fail(
                \sprintf(
                    'Function `%s()` is used in %s:%d.',
                    $function,
                    \str_replace($root . DIRECTORY_SEPARATOR, '', $file),
                    $line,
                ),
            );
        }

        self::assertTrue(true);
    }

    /**
     * @return iterable<string>
     */
    private function phpFiles(string $root): iterable
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            $path = $file->getPathname();
            if (
                !$file->isFile()
                || $file->getExtension() !== 'php'
                || \str_contains($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)
                || \str_contains($path, DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR)
            ) {
                continue;
            }

            yield $path;
        }
    }

    /**
     * @param list<string> $functions
     * @return array{string, int}|null
     */
    private function findForbiddenFunctionCall(string $code, array $functions): ?array
    {
        $tokens = \token_get_all($code);

        foreach ($tokens as $index => $token) {
            if (!\is_array($token)) {
                continue;
            }

            if (!$this->isForbiddenFunctionToken($token, $functions)) {
                continue;
            }

            $next = $this->nextSignificantToken($tokens, $index + 1);
            $previous = $this->previousSignificantToken($tokens, $index - 1);

            if (
                $next !== '('
                || $previous === '->'
                || $previous === '::'
                || (\is_array($previous) && $previous[0] === T_FUNCTION)
            ) {
                continue;
            }

            return [$this->tokenFunctionName($token), $token[2]];
        }

        return null;
    }

    /**
     * @param array{0:int,1:string,2:int} $token
     * @param list<string> $functions
     */
    private function isForbiddenFunctionToken(array $token, array $functions): bool
    {
        return \in_array($token[0], [T_STRING, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED], true)
            && \in_array($this->tokenFunctionName($token), $functions, true);
    }

    /**
     * @param array{0:int,1:string,2:int} $token
     */
    private function tokenFunctionName(array $token): string
    {
        return \ltrim(\substr(strrchr('\\' . $token[1], '\\') ?: $token[1], 1), '\\');
    }

    /**
     * @param list<mixed> $tokens
     */
    private function nextSignificantToken(array $tokens, int $index): mixed
    {
        for ($count = \count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];
            if (\is_array($token) && $token[0] === T_WHITESPACE) {
                continue;
            }

            return $token;
        }

        return null;
    }

    /**
     * @param list<mixed> $tokens
     */
    private function previousSignificantToken(array $tokens, int $index): mixed
    {
        for (; $index >= 0; $index--) {
            $token = $tokens[$index];
            if (\is_array($token) && $token[0] === T_WHITESPACE) {
                continue;
            }

            return $token;
        }

        return null;
    }
}
