<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\DTO;

use Temporal\Api\Common\V1\RetryPolicy;
use Temporal\Common\RetryOptions;

class RetryOptionsTestCase extends AbstractDTOMarshalling
{
    /**
     * @throws \ReflectionException
     */
    public function testMarshallingDefaultValues(): void
    {
        $dto = new RetryOptions();

        $expected = [
            'initialInterval' => null,
            'backoffCoefficient' => 2.0,
            'maximumInterval' => null,
            'maximumAttempts' => 0,
            'nonRetryableErrorTypes' => [],
        ];

        $this->assertSame($expected, $result = $this->marshal($dto));
        $json = \json_encode($result);

        /** @var RetryPolicy $message */
        $message = new RetryPolicy();
        $message->mergeFromJsonString($json);

        $this->assertSame(2.0, $message->getBackoffCoefficient());
    }

    public function testMarshallingIntervals(): void
    {
        $dto = RetryOptions::new()
            ->withMaximumAttempts(5)
            ->withBackoffCoefficient(3.0)
            ->withInitialInterval('10 seconds')
            ->withMaximumInterval('15 seconds');

        $expected = [
            'initialInterval' => '10s',
            'backoffCoefficient' => 3.0,
            'maximumInterval' => '15s',
            'maximumAttempts' => 5,
            'nonRetryableErrorTypes' => [],
        ];

        $this->assertSame($expected, $result = $this->marshal($dto));
        $json = \json_encode($result);

        /** @var RetryPolicy $message */
        $message = new RetryPolicy();
        $message->mergeFromJsonString($json);

        $this->assertSame(10, $message->getInitialInterval()->getSeconds());
        $this->assertSame(3.0, $message->getBackoffCoefficient());
        $this->assertSame(15, $message->getMaximumInterval()->getSeconds());
        $this->assertSame(5, $message->getMaximumAttempts());
    }
}
