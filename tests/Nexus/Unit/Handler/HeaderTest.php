<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit\Handler;

use Temporal\Nexus\Handler\OperationContext;
use Temporal\Nexus\Internal\Headers;
use Temporal\Worker\Environment\Environment;
use Temporal\Worker\Environment\EnvironmentInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Headers::class)]
#[CoversClass(OperationContext::class)]
final class HeaderTest extends TestCase
{
    private EnvironmentInterface $env;

    protected function setUp(): void
    {
        parent::setUp();
        $this->env = new Environment();
    }

    public function testOperationContextHeaders(): void
    {
        $context = new OperationContext(
            service: 'service',
            operation: 'operation',
            env: $this->env,
            headers: [
                'UPPER-CASE-HEADER' => 'UPPER-VALUE',
                'lower-case-header' => 'lower-value',
            ],
        );

        $this->verifyHeaders($context->headers->all());
    }

    /**
     * @param array<string, string> $headers
     */
    private function verifyHeaders(array $headers): void
    {
        self::assertSame('UPPER-VALUE', $headers['upper-case-header']);
        self::assertSame('lower-value', $headers['lower-case-header']);

        // Verify all keys are lowercase
        foreach ($headers as $key => $value) {
            self::assertSame(\strtolower($key), $key);
        }
    }
}
