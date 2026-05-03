<?php

/**
 * This file is part of Nexus RPC SDK for PHP package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit\Handler;

use Temporal\Nexus\Handler\AsyncOperationStartResult;
use Temporal\Nexus\Handler\OperationStartResult;
use Temporal\Nexus\Handler\SyncOperationStartResult;
use Temporal\Nexus\OperationInfo;
use Temporal\Nexus\OperationState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OperationStartResult::class)]
#[CoversClass(SyncOperationStartResult::class)]
#[CoversClass(AsyncOperationStartResult::class)]
final class OperationStartResultTest extends TestCase
{
    public function testSyncFactoryReturnsSyncSubclass(): void
    {
        $result = OperationStartResult::sync(null);

        self::assertInstanceOf(SyncOperationStartResult::class, $result);
        self::assertNull($result->value);
    }

    public function testSyncCarriesValue(): void
    {
        $result = OperationStartResult::sync('hello');

        self::assertInstanceOf(SyncOperationStartResult::class, $result);
        self::assertSame('hello', $result->value);
    }

    public function testAsyncFactoryReturnsAsyncSubclass(): void
    {
        $info = new OperationInfo('token-123', OperationState::Running);
        $result = OperationStartResult::async($info);

        self::assertInstanceOf(AsyncOperationStartResult::class, $result);
        self::assertSame($info, $result->info);
    }

    public function testPatternMatchWithInstanceOf(): void
    {
        $results = [
            OperationStartResult::sync('v'),
            OperationStartResult::async(new OperationInfo('tok', OperationState::Running)),
        ];

        $summary = \array_map(
            static fn(OperationStartResult $r): string => match (true) {
                $r instanceof SyncOperationStartResult  => "sync:{$r->value}",
                $r instanceof AsyncOperationStartResult => "async:{$r->info->token}",
            },
            $results,
        );

        self::assertSame(['sync:v', 'async:tok'], $summary);
    }
}
