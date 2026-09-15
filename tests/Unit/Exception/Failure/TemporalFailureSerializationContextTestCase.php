<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\Exception\Failure;

use PHPUnit\Framework\TestCase;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\WorkflowSerializationContext;
use Temporal\Exception\Failure\ApplicationFailure;

final class TemporalFailureSerializationContextTestCase extends TestCase
{
    public function testContextPropagatesToDetailsAndDirectCause(): void
    {
        $cause = new ApplicationFailure('cause', 'T', true, EncodedValues::fromValues(['cause']));
        $outer = new ApplicationFailure('outer', 'T', true, EncodedValues::fromValues(['outer']), previous: $cause);

        $context = new WorkflowSerializationContext('default', 'wf-1');
        $outer->setSerializationContext($context);

        self::assertSame($context, $outer->getSerializationContext());
        self::assertSame($context, $cause->getSerializationContext());

        $converter = DataConverter::createDefault();
        $outer->setDataConverter($converter);
        $cause->setDataConverter($converter);

        self::assertSame($context, self::contextOfDetails($outer));
        self::assertSame($context, self::contextOfDetails($cause));
    }

    public function testContextWalksPastNonTemporalCause(): void
    {
        $cause = new ApplicationFailure('cause', 'T', true, EncodedValues::fromValues(['cause']));
        $wrapper = new \RuntimeException('wrapper', 0, $cause);

        $outer = new ApplicationFailure('outer', 'T', true, EncodedValues::fromValues(['outer']), previous: $wrapper);

        $context = new WorkflowSerializationContext('default', 'wf-1');
        $outer->setSerializationContext($context);

        self::assertSame($context, $outer->getSerializationContext());
        self::assertSame($context, $cause->getSerializationContext());

        $converter = DataConverter::createDefault();
        $outer->setDataConverter($converter);
        $cause->setDataConverter($converter);

        self::assertSame($context, self::contextOfDetails($outer));
        self::assertSame($context, self::contextOfDetails($cause));
    }

    public function testContextThenConverterLeavesDetailsWithContext(): void
    {
        $failure = new ApplicationFailure('e', 'T', true, EncodedValues::fromValues(['x']));
        $context = new WorkflowSerializationContext('default', 'wf-order');

        $failure->setSerializationContext($context);
        $failure->setDataConverter(DataConverter::createDefault());

        self::assertSame($context, self::contextOfDetails($failure));
    }

    public function testConverterThenContextLeavesDetailsWithContext(): void
    {
        $failure = new ApplicationFailure('e', 'T', true, EncodedValues::fromValues(['x']));
        $context = new WorkflowSerializationContext('default', 'wf-order');

        $failure->setDataConverter(DataConverter::createDefault());
        $failure->setSerializationContext($context);

        self::assertSame($context, self::contextOfDetails($failure));
    }

    private static function contextOfDetails(ApplicationFailure $failure): ?WorkflowSerializationContext
    {
        $context = $failure->getDetails()->getSerializationContext();
        self::assertInstanceOf(WorkflowSerializationContext::class, $context);

        return $context;
    }
}
