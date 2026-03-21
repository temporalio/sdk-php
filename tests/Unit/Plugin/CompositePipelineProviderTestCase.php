<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Plugin;

use PHPUnit\Framework\TestCase;
use Temporal\Interceptor\PipelineProvider;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Internal\Interceptor\Interceptor;
use Temporal\Internal\Interceptor\Pipeline;
use Temporal\Plugin\CompositePipelineProvider;

/**
 * @group unit
 * @group plugin
 */
class CompositePipelineProviderTestCase extends TestCase
{
    public function testNoPluginInterceptorsUsesBaseProvider(): void
    {
        $interceptor = new TestInvokeInterceptor('A');

        $baseProvider = new SimplePipelineProvider([$interceptor]);
        $composite = new CompositePipelineProvider([], $baseProvider);

        $pipeline = $composite->getPipeline(TestInvokeInterceptor::class);
        /** @see TestInvokeInterceptor::__invoke() */
        $result = $pipeline->with(static fn(string $s) => $s, '__invoke')('_');

        self::assertSame('_A', $result);
    }

    public function testPluginInterceptorsPrependedToSimpleProvider(): void
    {
        $first = new TestOrderInterceptor('1');
        $second = new TestOrderInterceptor('2');

        $baseProvider = new SimplePipelineProvider([$second]);
        $composite = new CompositePipelineProvider([$first], $baseProvider);

        $pipeline = $composite->getPipeline(TestOrderInterceptor::class);
        /** @see TestOrderInterceptor::handle() */
        $result = $pipeline->with(static fn(string $s) => $s, 'handle')('_');

        // Plugin interceptor ($first) runs before base ($second)
        self::assertSame('_12', $result);
    }

    public function testPipelineCaching(): void
    {
        $composite = new CompositePipelineProvider([], new SimplePipelineProvider());

        $pipeline1 = $composite->getPipeline(TestOrderInterceptor::class);
        $pipeline2 = $composite->getPipeline(TestOrderInterceptor::class);

        self::assertSame($pipeline1, $pipeline2);
    }

    public function testCustomPipelineProviderWithPluginInterceptors(): void
    {
        // Custom provider that doesn't extend SimplePipelineProvider
        $customProvider = new class implements PipelineProvider {
            public function getPipeline(string $interceptorClass): Pipeline
            {
                return Pipeline::prepare([]);
            }
        };

        $interceptor = new TestOrderInterceptor('P');
        $composite = new CompositePipelineProvider([$interceptor], $customProvider);

        $pipeline = $composite->getPipeline(TestOrderInterceptor::class);
        /** @see TestOrderInterceptor::handle() */
        $result = $pipeline->with(static fn(string $s) => $s, 'handle')('_');

        self::assertSame('_P', $result);
    }

    public function testEmptyPluginInterceptorsWithCustomProvider(): void
    {
        $customProvider = new class implements PipelineProvider {
            public function getPipeline(string $interceptorClass): Pipeline
            {
                return Pipeline::prepare([new TestOrderInterceptor('X')]);
            }
        };

        $composite = new CompositePipelineProvider([], $customProvider);
        $pipeline = $composite->getPipeline(TestOrderInterceptor::class);
        /** @see TestOrderInterceptor::handle() */
        $result = $pipeline->with(static fn(string $s) => $s, 'handle')('_');

        self::assertSame('_X', $result);
    }
}

/**
 * Test interceptor that appends a tag to the input string.
 * @internal
 */
class TestOrderInterceptor implements Interceptor
{
    public function __construct(private readonly string $tag) {}

    public function handle(string $s, callable $next): string
    {
        return $next($s . $this->tag);
    }
}

/**
 * Test interceptor using __invoke.
 * @internal
 */
class TestInvokeInterceptor implements Interceptor
{
    public function __construct(private readonly string $tag) {}

    public function __invoke(string $s, callable $next): string
    {
        return $next($s . $this->tag);
    }
}
