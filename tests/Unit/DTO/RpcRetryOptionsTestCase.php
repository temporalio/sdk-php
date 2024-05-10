<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\DTO;

use Temporal\Client\Common\RpcRetryOptions;
use Temporal\Common\RetryOptions;

class RpcRetryOptionsTestCase extends AbstractDTOMarshalling
{
    public function testWithCongestionInitialInterval(): void
    {
        $dto = new RpcRetryOptions();

        // Check default value
        self::assertNull($dto->congestionInitialInterval);

        $new = $dto->withCongestionInitialInterval('10 seconds');
        // Check immutable method
        self::assertNotSame($dto, $new);
        self::assertNull($dto->congestionInitialInterval);
        self::assertInstanceOf(\DateInterval::class, $new->congestionInitialInterval);
        self::assertEquals(10_000, $new->congestionInitialInterval->totalMilliseconds);

        // Set null
        $new = $new->withCongestionInitialInterval(null);
        self::assertNull($new->congestionInitialInterval);
    }

    public function testWithCongestionInitialIntervalIncorrectValue(): void
    {
        $dto = new RpcRetryOptions();

        self::expectException(\InvalidArgumentException::class);

        $dto->withCongestionInitialInterval(false);
    }

    public function testMaximumJitterCoefficient(): void
    {
        $dto = new RpcRetryOptions();

        // Check default value
        self::assertSame(0.1, $dto->maximumJitterCoefficient);

        $new = $dto->withMaximumJitterCoefficient(0.5);
        // Check immutable method
        self::assertNotSame($dto, $new);
        self::assertSame(0.1, $dto->maximumJitterCoefficient);
        self::assertSame(0.5, $new->maximumJitterCoefficient);
    }

    public function testMaximumJitterCoefficientMaxLimits(): void
    {
        $dto = new RpcRetryOptions();

        self::expectException(\InvalidArgumentException::class);
        $dto->withMaximumJitterCoefficient(1.0);
    }

    public function testMaximumJitterCoefficientMinLimits(): void
    {
        $dto = new RpcRetryOptions();

        self::expectException(\InvalidArgumentException::class);
        $dto->withMaximumJitterCoefficient(-0.1);
    }

    public function testFromRetryOptions(): void
    {
        $policy = (new RetryOptions())
            ->withInitialInterval('5 seconds')
            ->withMaximumInterval('1 minute')
            ->withMaximumAttempts(10)
            ->withBackoffCoefficient(3.0)
            ->withNonRetryableExceptions(['RuntimeException']);

        $dto = RpcRetryOptions::fromRetryOptions($policy);

        self::assertEquals(5_000, $dto->initialInterval->totalMilliseconds);
        self::assertEquals(60_000, $dto->maximumInterval->totalMilliseconds);
        self::assertEquals(10, $dto->maximumAttempts);
        self::assertEquals(3.0, $dto->backoffCoefficient);
        self::assertEquals(['RuntimeException'], $dto->nonRetryableExceptions);
    }

    public function testFromSameRetryOptions(): void
    {
        $policy = (new RpcRetryOptions())
            ->withInitialInterval('5 seconds')
            ->withMaximumInterval('1 minute')
            ->withMaximumAttempts(10)
            ->withBackoffCoefficient(3.0)
            ->withNonRetryableExceptions(['RuntimeException']);

        $dto = RpcRetryOptions::fromRetryOptions($policy);

        self::assertSame($policy, $dto);
    }
}
