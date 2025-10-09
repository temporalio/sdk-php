<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App;

final class ClassLocator
{
    /**
     * @param non-empty-string $dir
     * @param non-empty-string $namespace
     * @return iterable<class-string>
     */
    public static function loadTestCases(string $dir, string $namespace): iterable
    {
        $dir = \realpath($dir);
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));

        /** @var \SplFileInfo $_ */
        foreach ($files as $path => $_) {
            if (!\is_file($path) || !\str_ends_with($path, '.php')) {
                continue;
            }

            require_once $path;
        }

        yield from self::findTestClasses($namespace);
    }

    /**
     * @template T
     * @param non-empty-string $namespace
     * @param class-string<T> $baseClass
     * @return \Traversable<int, class-string<T>>
     */
    public static function findTestClasses(string $namespace, string $baseClass = TestCase::class): \Traversable
    {
        yield from \array_filter(
            \get_declared_classes(),
            static fn(string $class): bool => \str_starts_with($class, "$namespace\\") && \is_a(
                $class,
                $baseClass,
                true,
            ),
        );
    }
}
