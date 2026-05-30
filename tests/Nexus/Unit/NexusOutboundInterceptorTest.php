<?php

/**
 * This file is part of Nexus RPC SDK for PHP package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Temporal\Interceptor\NexusOperationOutbound\GetInfoInput;
use Temporal\Interceptor\NexusOperationOutboundCallsInterceptor;
use Temporal\Interceptor\Trait\NexusOperationOutboundCallsInterceptorTrait;
use Temporal\Internal\Interceptor\Pipeline;
use Temporal\Internal\Nexus\NexusContext;
use Temporal\Nexus\Nexus;
use Temporal\Nexus\NexusOperationContext;

#[CoversClass(Nexus::class)]
#[CoversClass(NexusOperationOutboundCallsInterceptor::class)]
final class NexusOutboundInterceptorTest extends TestCase
{
    protected function tearDown(): void
    {
        Nexus::setCurrentContext(null);
        parent::tearDown();
    }

    public function testGetInfoPassesThroughInterceptorChain(): void
    {
        $log = [];
        $record = static function (string $name) use (&$log): NexusOperationOutboundCallsInterceptor {
            return new class($name, $log) implements NexusOperationOutboundCallsInterceptor {
                use NexusOperationOutboundCallsInterceptorTrait;

                /** @param list<string> $log */
                public function __construct(
                    private readonly string $name,
                    private array &$log,
                ) {}

                public function getInfo(GetInfoInput $input, callable $next): NexusOperationContext
                {
                    $this->log[] = "enter:{$this->name}";
                    $info = $next($input);
                    $this->log[] = "exit:{$this->name}";
                    return $info;
                }
            };
        };

        $info = new NexusOperationContext('ns', 'tq');
        Nexus::setCurrentContext(new NexusContext(
            operation: $info,
            outboundPipeline: Pipeline::prepare([$record('A'), $record('B')]),
        ));

        $result = Nexus::getOperationContext();

        self::assertSame($info, $result);
        self::assertSame(['enter:A', 'enter:B', 'exit:B', 'exit:A'], $log);
    }

    public function testInterceptorCanRewriteReturnedInfo(): void
    {
        $rewriting = new class implements NexusOperationOutboundCallsInterceptor {
            use NexusOperationOutboundCallsInterceptorTrait;

            public function getInfo(GetInfoInput $input, callable $next): NexusOperationContext
            {
                $next($input);
                return new NexusOperationContext('rewritten-ns', 'rewritten-tq');
            }
        };

        Nexus::setCurrentContext(new NexusContext(
            operation: new NexusOperationContext('ns', 'tq'),
            outboundPipeline: Pipeline::prepare([$rewriting]),
        ));

        $result = Nexus::getOperationContext();

        self::assertSame('rewritten-ns', $result->namespace);
        self::assertSame('rewritten-tq', $result->taskQueue);
    }

    public function testGetInfoReturnsInfoDirectlyWithoutPipeline(): void
    {
        $info = new NexusOperationContext('ns', 'tq');
        Nexus::setCurrentContext(new NexusContext(operation: $info));

        self::assertSame($info, Nexus::getOperationContext());
    }

    public function testGetInfoThrowsOutsideDispatch(): void
    {
        $this->expectException(\LogicException::class);
        Nexus::getOperationContext();
    }
}
