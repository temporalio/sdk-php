<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit\Internal\Failure;

use Temporal\Api\Enums\V1\NexusHandlerErrorRetryBehavior;
use Temporal\Nexus\Exception\RetryBehavior;
use Temporal\Nexus\Internal\Failure\NexusFailureConverter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(NexusFailureConverter::class)]
final class NexusFailureConverterTest extends TestCase
{
    /** @return iterable<string, array{0: RetryBehavior, 1: int}> */
    public static function retryBehaviorMatrix(): iterable
    {
        yield 'Unspecified' => [
            RetryBehavior::Unspecified,
            NexusHandlerErrorRetryBehavior::NEXUS_HANDLER_ERROR_RETRY_BEHAVIOR_UNSPECIFIED,
        ];
        yield 'Retryable' => [
            RetryBehavior::Retryable,
            NexusHandlerErrorRetryBehavior::NEXUS_HANDLER_ERROR_RETRY_BEHAVIOR_RETRYABLE,
        ];
        yield 'NonRetryable' => [
            RetryBehavior::NonRetryable,
            NexusHandlerErrorRetryBehavior::NEXUS_HANDLER_ERROR_RETRY_BEHAVIOR_NON_RETRYABLE,
        ];
    }

    #[DataProvider('retryBehaviorMatrix')]
    public function testMapRetryBehavior(RetryBehavior $behavior, int $expectedProto): void
    {
        self::assertSame($expectedProto, NexusFailureConverter::mapRetryBehavior($behavior));
    }
}
