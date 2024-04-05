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
            'initial_interval' => null,
            'backoff_coefficient' => 2.0,
            'maximum_interval' => null,
            'maximum_attempts' => 0,
            'non_retryable_error_types' => [],
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
            'initial_interval' => ['seconds' => 10, 'nanos' => 0],
            'backoff_coefficient' => 3.0,
            'maximum_interval' => ['seconds' => 15, 'nanos' => 0],
            'maximum_attempts' => 5,
            'non_retryable_error_types' => [],
        ];

        $this->assertSame($expected, $this->marshal($dto));
    }

    public function testUnmarshalLegacyIntervals(): void
    {
        $expected = [
            'initial_interval' => 10_000_000_000,
            'backoff_coefficient' => 3.0,
            'maximum_interval' => 15_000_000_000,
            'maximum_attempts' => 5,
            'non_retryable_error_types' => [],
        ];

        $this->unmarshal($expected, $unmarshalled = new RetryOptions());

        self::assertSame(10.0, $unmarshalled->initialInterval->totalSeconds);
        self::assertSame(15.0, $unmarshalled->maximumInterval->totalSeconds);
        self::assertSame(3.0, $unmarshalled->backoffCoefficient);
        self::assertSame(5, $unmarshalled->maximumAttempts);
    }
}
