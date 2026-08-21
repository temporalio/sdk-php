<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit\Handler;

use Temporal\Nexus\Handler\ClosureMethodCancellationListener;
use Temporal\Nexus\Handler\LinkCollection;
use Temporal\Nexus\Handler\MethodCanceller;
use Temporal\Nexus\Handler\OperationContext;
use Temporal\Nexus\Link;
use Temporal\Worker\Environment\Environment;
use Temporal\Worker\Environment\EnvironmentInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OperationContext::class)]
final class OperationContextTest extends TestCase
{
    private EnvironmentInterface $env;

    protected function setUp(): void
    {
        parent::setUp();
        $this->env = new Environment();
    }

    public function testLinksAddAndSet(): void
    {
        $ctx = new OperationContext(service: 'service', operation: 'operation', env: $this->env);
        $ctx->links->add(new Link('http://somepath?k=v', 'com.example.MyResource'));

        self::assertEquals(
            [new Link('http://somepath?k=v', 'com.example.MyResource')],
            $ctx->links->all(),
        );
    }

    public function testDeadline(): void
    {
        $deadline = new \DateTimeImmutable('+1 second');
        $ctx = new OperationContext(
            service: 'service',
            operation: 'operation',
            env: $this->env,
            deadline: $deadline,
        );

        self::assertSame($deadline, $ctx->deadline);
    }

    public function testConstructorNormalizesHeaders(): void
    {
        $ctx = new OperationContext(
            service: 's',
            operation: 'o',
            env: $this->env,
            headers: [
                'Content-Type' => 'text/plain',
                'UPPER-CASE-HEADER' => 'UPPER-VALUE',
                'lower-case-header' => 'lower-value',
            ],
        );

        self::assertSame(
            [
                'content-type' => 'text/plain',
                'upper-case-header' => 'UPPER-VALUE',
                'lower-case-header' => 'lower-value',
            ],
            $ctx->headers->all(),
        );
    }

    public function testLinksPassedAsExistingCollectionAreUsedAsIs(): void
    {
        $collection = new LinkCollection([new Link('shared', 't')]);
        $ctx = new OperationContext(service: 's', operation: 'o', env: $this->env, links: $collection);

        $collection->add(new Link('added-outside', 't'));
        // The same collection was injected, so external additions are visible.
        self::assertSame($collection, $ctx->links);
        self::assertCount(2, $ctx->links->all());
    }

    public function testMethodCancellationDegradesWithoutCanceller(): void
    {
        $ctx = new OperationContext(service: 's', operation: 'o', env: $this->env);

        self::assertFalse($ctx->isMethodCancelled());
        self::assertNull($ctx->getMethodCancellationReason());

        // Listener registration without a canceller is a silent no-op.
        $called = false;
        $listener = ClosureMethodCancellationListener::fromCallable(
            static function () use (&$called): void {
                $called = true;
            },
        );
        $ctx->addMethodCancellationListener($listener);
        $ctx->removeMethodCancellationListener($listener);
        self::assertFalse($called);
    }

    public function testMethodCancellationPropagatesFromCanceller(): void
    {
        $canceller = new MethodCanceller($this->env);
        $ctx = new OperationContext(
            service: 's',
            operation: 'o',
            env: $this->env,
            methodCanceller: $canceller,
        );

        self::assertFalse($ctx->isMethodCancelled());

        $fired = false;
        $ctx->addMethodCancellationListener(ClosureMethodCancellationListener::fromCallable(
            static function () use (&$fired): void {
                $fired = true;
            },
        ));

        $canceller->cancel('shutdown');

        self::assertTrue($ctx->isMethodCancelled());
        self::assertSame('shutdown', $ctx->getMethodCancellationReason());
        self::assertTrue($fired);
    }

    public function testIsMethodCancelledTripsOnDeadlineEvenWithoutCanceller(): void
    {
        // No canceller attached — but deadline-based trip must still be observable so
        // long-running handlers that only poll isMethodCancelled() degrade correctly on
        // transports without method-cancel support.
        $ctx = new OperationContext(
            service: 's',
            operation: 'o',
            env: $this->env,
            deadline: new \DateTimeImmutable('-1 second'),
        );

        self::assertTrue($ctx->isMethodCancelled());
        self::assertStringContainsString('deadline exceeded', (string) $ctx->getMethodCancellationReason());
    }

    public function testExplicitCancellerReasonBeatsDeadline(): void
    {
        $canceller = new MethodCanceller($this->env);
        $canceller->cancel('shutdown');
        $ctx = new OperationContext(
            service: 's',
            operation: 'o',
            env: $this->env,
            deadline: new \DateTimeImmutable('-1 second'),
            methodCanceller: $canceller,
        );

        self::assertSame('shutdown', $ctx->getMethodCancellationReason());
    }
}
