<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\DTO;

use Temporal\Activity\LocalActivityOptions;
use Temporal\Common\MethodRetry;
use Temporal\Common\RetryOptions;

class LocalActivityOptionsTestCase extends AbstractDTOMarshalling
{
    public function testMergeWithMethodRetryFillsDefaultRetryOptions(): void
    {
        $dto = LocalActivityOptions::new()
            ->withRetryOptions(RetryOptions::new())
            ->mergeWith(new MethodRetry(maximumAttempts: 5));

        $this->assertSame(5, $dto->retryOptions->maximumAttempts);
    }

    public function testMergeWithMethodRetryCreatesRetryOptionsWhenNull(): void
    {
        $dto = LocalActivityOptions::new()->mergeWith(new MethodRetry(maximumAttempts: 5));

        $this->assertNotNull($dto->retryOptions);
        $this->assertSame(5, $dto->retryOptions->maximumAttempts);
    }

    public function testMergeWithMethodRetryKeepsUserDefinedFields(): void
    {
        $methodRetry = new MethodRetry(maximumAttempts: 5, maximumInterval: 30);
        $dto = LocalActivityOptions::new()
            ->withRetryOptions(RetryOptions::new()->withMaximumAttempts(1))
            ->mergeWith($methodRetry);

        $this->assertSame(1, $dto->retryOptions->maximumAttempts);
        $this->assertSame($methodRetry->maximumInterval, $dto->retryOptions->maximumInterval);
    }

    public function testMergeWithNullRetryDoesNotChangeRetryOptions(): void
    {
        $retry = RetryOptions::new()->withMaximumAttempts(7);
        $dto = LocalActivityOptions::new()->withRetryOptions($retry)->mergeWith(null);

        $this->assertSame(7, $dto->retryOptions->maximumAttempts);
    }
}
