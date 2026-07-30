<?php

declare(strict_types=1);

namespace Temporal\Tests\Arch;

use PHPUnit\Architecture\ArchitectureAsserts;
use PHPUnit\Framework\TestCase;

final class ArchTest extends TestCase
{
    protected array $excludedPaths = [
        'vendor',
        'tests',
    ];

    use ArchitectureAsserts;

    public function testForgottenDebugFunctions(): void
    {
        $functions = ['dump', 'trap', 'tr', 'td', 'var_dump'];
        $layer = $this->layer();

        foreach ($layer as $object) {
            foreach ($object->uses as $use) {
                foreach ($functions as $function) {
                    $function === $use and throw new \Exception(
                        \sprintf(
                            'Function `%s()` is used in %s.',
                            $function,
                            $object->name,
                        ),
                    );
                }
            }
        }

        $this->assertTrue(true);
    }

    public function testFiberFacadeKeepsParityWithWorkflow(): void
    {
        $base = $this->publicStaticMethods(\Temporal\Workflow::class);
        $fiber = $this->publicStaticMethods(\Temporal\Experiments\Fibers\Workflow::class);

        // Internal/magic entry points that intentionally have no Fiber-facade counterpart.
        $internalOnly = ['__callStatic', 'getContextId', 'setCurrentContext'];
        // Fiber-only helpers that expose raw promises for combinator use.
        $fiberOnlyExtras = ['gather', 'timerPromise'];

        $missing = \array_values(\array_diff($base, $fiber, $internalOnly));
        $extra = \array_values(\array_diff($fiber, $base, $fiberOnlyExtras));

        $this->assertSame([], $missing, 'Fiber facade is missing base Workflow methods: ' . \implode(', ', $missing));
        $this->assertSame([], $extra, 'Fiber facade has undocumented extra methods: ' . \implode(', ', $extra));
    }

    /**
     * @return list<string>
     */
    private function publicStaticMethods(string $class): array
    {
        $methods = [];
        foreach ((new \ReflectionClass($class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic()) {
                $methods[] = $method->getName();
            }
        }

        \sort($methods);

        return $methods;
    }
}
