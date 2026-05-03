<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit\Attribute;

use Temporal\Nexus\Attribute\AsyncOperation;
use Temporal\Nexus\Attribute\Operation;
use Temporal\Nexus\Attribute\OperationCancel;
use Temporal\Nexus\Attribute\Service;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Operation::class)]
#[CoversClass(AsyncOperation::class)]
#[CoversClass(OperationCancel::class)]
#[CoversClass(Service::class)]
final class AttributesTest extends TestCase
{
    public function testOperationDefaultsToEmptyName(): void
    {
        $op = new Operation();
        self::assertSame('', $op->name);
    }

    public function testOperationHoldsName(): void
    {
        $op = new Operation('myOp');
        self::assertSame('myOp', $op->name);
    }

    public function testAsyncOperationDefaultsToEmptyNameAndOutput(): void
    {
        $op = new AsyncOperation();
        self::assertSame('', $op->name);
        self::assertSame('', $op->output);
    }

    public function testAsyncOperationHoldsNameAndOutput(): void
    {
        $op = new AsyncOperation(name: 'myOp', output: 'string');
        self::assertSame('myOp', $op->name);
        self::assertSame('string', $op->output);
    }

    public function testOperationCancelHoldsTargetOperation(): void
    {
        $cancel = new OperationCancel(operation: 'startJob');
        self::assertSame('startJob', $cancel->operation);
    }

    public function testServiceDefaultsToEmptyName(): void
    {
        $s = new Service();
        self::assertSame('', $s->name);
    }

    public function testServiceHoldsName(): void
    {
        $s = new Service('mySvc');
        self::assertSame('mySvc', $s->name);
    }
}
