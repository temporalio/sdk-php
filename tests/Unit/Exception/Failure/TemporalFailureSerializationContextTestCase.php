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
        $causeDetails = EncodedValues::fromValues(['cause']);
        $cause = new ApplicationFailure('cause', 'T', true, $causeDetails);

        $outerDetails = EncodedValues::fromValues(['outer']);
        $outer = new ApplicationFailure('outer', 'T', true, $outerDetails, previous: $cause);

        $context = new WorkflowSerializationContext('default', 'wf-1');
        $outer->setSerializationContext($context);

        self::assertSame($context, $outerDetails->getSerializationContext());
        self::assertSame($context, $causeDetails->getSerializationContext());
    }

    public function testContextWalksPastNonTemporalCause(): void
    {
        $causeDetails = EncodedValues::fromValues(['cause']);
        $cause = new ApplicationFailure('cause', 'T', true, $causeDetails);
        $wrapper = new \RuntimeException('wrapper', 0, $cause);

        $outerDetails = EncodedValues::fromValues(['outer']);
        $outer = new ApplicationFailure('outer', 'T', true, $outerDetails, previous: $wrapper);

        $context = new WorkflowSerializationContext('default', 'wf-1');
        $outer->setSerializationContext($context);

        self::assertSame($context, $outerDetails->getSerializationContext());
        self::assertSame($context, $causeDetails->getSerializationContext());
    }
}
