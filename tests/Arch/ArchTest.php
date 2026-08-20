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
}
