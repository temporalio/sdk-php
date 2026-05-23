<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Plugin;

use PHPUnit\Framework\TestCase;
use Temporal\Client\ClientOptions;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Plugin\ScheduleClientPluginContext;

/**
 * @group unit
 * @group plugin
 */
class ScheduleClientPluginContextTestCase extends TestCase
{
    public function testBuilderPattern(): void
    {
        $options = new ClientOptions();
        $context = new ScheduleClientPluginContext($options);

        self::assertSame($options, $context->getClientOptions());
        self::assertNull($context->getDataConverter());
    }

    public function testSetters(): void
    {
        $context = new ScheduleClientPluginContext(new ClientOptions());
        $newOptions = new ClientOptions();
        $converter = $this->createMock(DataConverterInterface::class);

        $result = $context
            ->setClientOptions($newOptions)
            ->setDataConverter($converter);

        self::assertSame($context, $result);
        self::assertSame($newOptions, $context->getClientOptions());
        self::assertSame($converter, $context->getDataConverter());
    }
}
