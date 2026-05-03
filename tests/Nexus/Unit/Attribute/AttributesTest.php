<?php

/**
 * This file is part of Nexus RPC SDK for PHP package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit\Attribute;

use Temporal\Nexus\Attribute\Operation;
use Temporal\Nexus\Attribute\OperationImpl;
use Temporal\Nexus\Attribute\Service;
use Temporal\Nexus\Attribute\ServiceImpl;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Operation::class)]
#[CoversClass(OperationImpl::class)]
#[CoversClass(Service::class)]
#[CoversClass(ServiceImpl::class)]
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

    public function testOperationImplIsConstructible(): void
    {
        new OperationImpl();
        self::assertTrue(true);
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

    public function testServiceImplHoldsServiceClass(): void
    {
        $si = new ServiceImpl('SomeIface');
        self::assertSame('SomeIface', $si->service);
    }
}
