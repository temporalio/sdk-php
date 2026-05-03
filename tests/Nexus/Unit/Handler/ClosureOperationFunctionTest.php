<?php

/**
 * This file is part of Nexus RPC SDK for PHP package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit\Handler;

use Temporal\Nexus\Handler\ClosureOperationFunction;
use Temporal\Nexus\Handler\OperationContext;
use Temporal\Nexus\Handler\OperationStartDetails;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ClosureOperationFunction::class)]
#[UsesClass(OperationContext::class)]
#[UsesClass(OperationStartDetails::class)]
final class ClosureOperationFunctionTest extends TestCase
{
    public function testInvokeForwardsArguments(): void
    {
        $captured = null;
        $f = ClosureOperationFunction::fromCallable(
            static function (OperationContext $ctx, OperationStartDetails $d, mixed $input) use (&$captured): string {
                $captured = [$ctx->service, $ctx->operation, $d->requestId, $input];
                return 'ok';
            },
        );

        $result = $f(
            new OperationContext(service: 's', operation: 'op'),
            new OperationStartDetails(requestId: 'r1'),
            'payload',
        );

        self::assertSame('ok', $result);
        self::assertSame(['s', 'op', 'r1', 'payload'], $captured);
    }

    public function testGetClosureReturnsTheWrappedClosure(): void
    {
        $closure = static fn() => null;
        $f = ClosureOperationFunction::fromCallable($closure);
        // wrapped by `$callable(...)` which produces a fresh Closure instance
        self::assertInstanceOf(\Closure::class, $f->getClosure());
    }
}
