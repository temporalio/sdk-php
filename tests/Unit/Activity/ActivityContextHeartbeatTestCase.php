<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Activity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Temporal\Activity\ActivityInfo;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\EncodedValues;
use Temporal\Interceptor\Header;
use Temporal\Internal\Activity\ActivityContext;
use Temporal\Worker\Transport\RPCConnectionInterface;

#[CoversClass(ActivityContext::class)]
final class ActivityContextHeartbeatTestCase extends TestCase
{
    public function testHeartbeatCallsTheHeartbeatMethodWithTheTaskTokenOfTheGivenInfo(): void
    {
        $info = new ActivityInfo();
        $info->taskToken = 'token';
        $rpc = $this->createMock(RPCConnectionInterface::class);
        $rpc->expects($this->once())
            ->method('call')
            ->with('temporal.RecordActivityHeartbeat', $this->callback(
                static fn(array $arguments): bool => $arguments['taskToken'] === \base64_encode('token'),
            ))
            ->willReturn([]);
        $context = new ActivityContext($rpc, DataConverter::createDefault(), EncodedValues::empty(), Header::empty(), null, $info);

        $context->heartbeat('progress');

        $this->assertSame($info, $context->getInfo());
    }
}
