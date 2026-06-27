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
        $outer = $outer->withSerializationContext($context);

        self::assertSame($context, $outer->getSerializationContext());
        self::assertSame($context, $outer->getDetails()->getSerializationContext());
        self::assertSame($context, $cause->getSerializationContext());
        self::assertSame($context, $cause->getDetails()->getSerializationContext());
    }

    public function testContextWalksPastNonTemporalCause(): void
    {
        $cause = new ApplicationFailure('cause', 'T', true, EncodedValues::fromValues(['cause']));
        $wrapper = new \RuntimeException('wrapper', 0, $cause);

        $outer = new ApplicationFailure('outer', 'T', true, EncodedValues::fromValues(['outer']), previous: $wrapper);

        $context = new WorkflowSerializationContext('default', 'wf-1');
        $outer = $outer->withSerializationContext($context);

        self::assertSame($context, $outer->getSerializationContext());
        self::assertSame($context, $outer->getDetails()->getSerializationContext());
        self::assertSame($context, $cause->getSerializationContext());
        self::assertSame($context, $cause->getDetails()->getSerializationContext());
    }
}
