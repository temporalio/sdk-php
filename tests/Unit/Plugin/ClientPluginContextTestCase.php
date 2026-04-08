<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Plugin;

use PHPUnit\Framework\TestCase;
use Temporal\Client\ClientOptions;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Interceptor\WorkflowClientCallsInterceptor;
use Temporal\Interceptor\Trait\WorkflowClientCallsInterceptorTrait;
use Temporal\Plugin\ClientPluginContext;

/**
 * @group unit
 * @group plugin
 */
class ClientPluginContextTestCase extends TestCase
{
    public function testBuilderPattern(): void
    {
        $options = new ClientOptions();
        $context = new ClientPluginContext($options);

        self::assertSame($options, $context->getClientOptions());
        self::assertNull($context->getDataConverter());
        self::assertSame([], $context->getInterceptors());
    }

    public function testSetters(): void
    {
        $context = new ClientPluginContext(new ClientOptions());
        $newOptions = new ClientOptions();
        $converter = $this->createMock(DataConverterInterface::class);

        $result = $context
            ->setClientOptions($newOptions)
            ->setDataConverter($converter);

        self::assertSame($context, $result);
        self::assertSame($newOptions, $context->getClientOptions());
        self::assertSame($converter, $context->getDataConverter());
    }

    public function testAddInterceptor(): void
    {
        $context = new ClientPluginContext(new ClientOptions());

        $interceptor = new class implements WorkflowClientCallsInterceptor {
            use WorkflowClientCallsInterceptorTrait;
        };
        $result = $context->addInterceptor($interceptor);

        self::assertSame($context, $result);
        self::assertCount(1, $context->getInterceptors());
        self::assertSame($interceptor, $context->getInterceptors()[0]);
    }

    public function testSetInterceptors(): void
    {
        $context = new ClientPluginContext(new ClientOptions());

        $interceptor = new class implements WorkflowClientCallsInterceptor {
            use WorkflowClientCallsInterceptorTrait;
        };
        $context->setInterceptors([$interceptor]);

        self::assertCount(1, $context->getInterceptors());
    }
}
