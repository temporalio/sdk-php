<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit\Handler;

use Temporal\Internal\Declaration\Prototype\NexusServicePrototype;
use Temporal\Nexus\Handler\ClosureMethodCancellationListener;
use Temporal\Nexus\Handler\LinkCollection;
use Temporal\Nexus\Handler\MethodCanceller;
use Temporal\Nexus\Handler\OperationContext;
use Spiral\Attributes\AttributeReader;
use Temporal\Internal\Declaration\Reader\NexusServiceReader;
use Temporal\Nexus\Link;
use Temporal\Tests\Nexus\Fixture\ServiceDefinition\ValidServiceWithOperationsForContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OperationContext::class)]
#[UsesClass(NexusServiceReader::class)]
#[UsesClass(NexusServicePrototype::class)]
final class OperationContextTest extends TestCase
{
    public function testLinksAddAndSet(): void
    {
        $ctx = new OperationContext(service: 'service', operation: 'operation');
        $ctx->links->add(new Link('http://somepath?k=v', 'com.example.MyResource'));

        self::assertEquals(
            [new Link('http://somepath?k=v', 'com.example.MyResource')],
            $ctx->links->all(),
        );

        $ctx->links->replaceAll();
        self::assertEquals([], $ctx->links->all());
    }

    public function testDeadline(): void
    {
        $deadline = new \DateTimeImmutable('+1 second');
        $ctx = new OperationContext(
            service: 'service',
            operation: 'operation',
            deadline: $deadline,
        );

        self::assertSame($deadline, $ctx->deadline);
    }

    public function testConstructorNormalizesHeaders(): void
    {
        $ctx = new OperationContext(
            service: 's',
            operation: 'o',
            headers: ['Content-Type' => 'text/plain'],
        );
        self::assertSame(['content-type' => 'text/plain'], $ctx->headers);
    }

    public function testCreateNormalizesHeaders(): void
    {
        $ctx = new OperationContext(
            service: 's',
            operation: 'o',
            headers: ['X-Trace-Id' => 'abc'],
        );
        self::assertSame(['x-trace-id' => 'abc'], $ctx->headers);
    }

    public function testWithServiceDefinitionKeepsHeadersDeadlineAndService(): void
    {
        $deadline = new \DateTimeImmutable('+1 minute');
        $ctx = new OperationContext(
            service: 's',
            operation: 'o',
            headers: ['X' => '1'],
            deadline: $deadline,
        );

        $def = (new NexusServiceReader(new AttributeReader()))->fromClass(ValidServiceWithOperationsForContext::class);
        $ctx2 = $ctx->withServiceDefinition($def);

        self::assertSame($ctx->service, $ctx2->service);
        self::assertSame($ctx->operation, $ctx2->operation);
        self::assertSame($ctx->headers, $ctx2->headers);
        self::assertSame($ctx->deadline, $ctx2->deadline);
        self::assertSame($def, $ctx2->serviceDefinition);
    }

    public function testWithServiceDefinitionSharesLinksBuffer(): void
    {
        $def = (new NexusServiceReader(new AttributeReader()))->fromClass(ValidServiceWithOperationsForContext::class);

        $ctx = new OperationContext(
            service: 's',
            operation: 'o',
            links: [new Link('url1', 't1')],
        );
        $ctx2 = $ctx->withServiceDefinition($def);

        $ctx2->links->add(new Link('url2', 't2'));

        // The collection is deliberately shared: both contexts observe the same list
        // so that links emitted inside interceptors flow back to the transport.
        self::assertSame($ctx->links, $ctx2->links);
        self::assertCount(2, $ctx->links->all());
        self::assertSame('url2', $ctx->links->all()[1]->uri);
    }

    public function testReplaceAllLinksReplacesContents(): void
    {
        $ctx = new OperationContext(service: 's', operation: 'o');
        $ctx->links->add(new Link('a', 'x'), new Link('b', 'y'));
        $ctx->links->replaceAll(new Link('c', 'z'));

        self::assertCount(1, $ctx->links->all());
        self::assertSame('c', $ctx->links->all()[0]->uri);
    }

    public function testLinksPassedAsExistingCollectionAreUsedAsIs(): void
    {
        $collection = new LinkCollection([new Link('shared', 't')]);
        $ctx = new OperationContext(service: 's', operation: 'o', links: $collection);

        $collection->add(new Link('added-outside', 't'));
        // The same collection was injected, so external additions are visible.
        self::assertSame($collection, $ctx->links);
        self::assertCount(2, $ctx->links->all());
    }

    public function testMethodCancellationDegradesWithoutCanceller(): void
    {
        $ctx = new OperationContext(service: 's', operation: 'o');

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
        $canceller = new MethodCanceller();
        $ctx = new OperationContext(
            service: 's',
            operation: 'o',
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

    public function testIsDeadlineExceededFalseWhenFutureDeadline(): void
    {
        $ctx = new OperationContext(
            service: 's',
            operation: 'o',
            deadline: new \DateTimeImmutable('+1 hour'),
        );

        self::assertFalse($ctx->isDeadlineExceeded());
        self::assertFalse($ctx->isMethodCancelled());
    }

    public function testIsDeadlineExceededTrueWhenPast(): void
    {
        $ctx = new OperationContext(
            service: 's',
            operation: 'o',
            deadline: new \DateTimeImmutable('-1 second'),
        );

        self::assertTrue($ctx->isDeadlineExceeded());
    }

    public function testIsMethodCancelledTripsOnDeadlineEvenWithoutCanceller(): void
    {
        // No canceller attached — but deadline-based trip must still be observable so
        // long-running handlers that only poll isMethodCancelled() degrade correctly on
        // transports without method-cancel support.
        $ctx = new OperationContext(
            service: 's',
            operation: 'o',
            deadline: new \DateTimeImmutable('-1 second'),
        );

        self::assertTrue($ctx->isMethodCancelled());
        self::assertStringContainsString('deadline exceeded', (string) $ctx->getMethodCancellationReason());
    }

    public function testExplicitCancellerReasonBeatsDeadline(): void
    {
        $canceller = new MethodCanceller();
        $canceller->cancel('shutdown');
        $ctx = new OperationContext(
            service: 's',
            operation: 'o',
            deadline: new \DateTimeImmutable('-1 second'),
            methodCanceller: $canceller,
        );

        self::assertSame('shutdown', $ctx->getMethodCancellationReason());
    }

    public function testIsDeadlineExceededWithNoDeadline(): void
    {
        $ctx = new OperationContext(service: 's', operation: 'o');

        self::assertFalse($ctx->isDeadlineExceeded());
    }

    public function testWithServiceDefinitionPreservesMethodCanceller(): void
    {
        $canceller = new MethodCanceller();
        $ctx = new OperationContext(
            service: 's',
            operation: 'o',
            methodCanceller: $canceller,
        );
        $def = (new NexusServiceReader(new AttributeReader()))->fromClass(ValidServiceWithOperationsForContext::class);

        $ctx2 = $ctx->withServiceDefinition($def);
        $canceller->cancel('reload');

        // Both contexts share the same canceller and observe the cancellation.
        self::assertTrue($ctx->isMethodCancelled());
        self::assertTrue($ctx2->isMethodCancelled());
        self::assertSame('reload', $ctx2->getMethodCancellationReason());
    }
}
