<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit\Internal\Failure;

use Temporal\Nexus\Exception\ErrorType;
use Temporal\Nexus\Exception\HandlerException;
use Temporal\Nexus\Exception\NexusException;
use Temporal\Nexus\Exception\OperationException;
use Temporal\Nexus\Exception\RetryBehavior;
use Temporal\Nexus\Internal\Failure\NexusFailureConverter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NexusFailureConverter::class)]
#[UsesClass(OperationException::class)]
#[UsesClass(HandlerException::class)]
#[UsesClass(NexusException::class)]
final class NexusFailureConverterTest extends TestCase
{
    public function testOperationExceptionIsPackedDirectlyIntoProto(): void
    {
        $e = OperationException::failed('boom');

        $opError = NexusFailureConverter::operationExceptionToProto($e);

        // proto field carries the state directly
        self::assertSame('failed', $opError->getOperationState());

        $failure = $opError->getFailure();
        self::assertNotNull($failure);
        self::assertSame('boom', $failure->getMessage());

        // metadata.type carries the canonical Nexus-spec marker, NOT the PHP class
        $meta = \iterator_to_array($failure->getMetadata());
        self::assertSame(NexusFailureConverter::OPERATION_ERROR_TYPE, $meta['type']);

        // details JSON has canonical state + flat traceback
        $details = \json_decode($failure->getDetails(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('failed', $details[NexusFailureConverter::DETAILS_STATE_KEY]);
        self::assertArrayHasKey(NexusFailureConverter::DETAILS_TRACEBACK_KEY, $details);
    }

    public function testOperationExceptionWithoutTracebackOmitsCauseChain(): void
    {
        $e = OperationException::canceled('x');

        $opError = NexusFailureConverter::operationExceptionToProto($e, includeTraceback: false);
        $details = \json_decode($opError->getFailure()->getDetails(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame(['state' => 'canceled'], $details);
    }

    public function testHandlerExceptionIsPackedDirectlyIntoProto(): void
    {
        $e = HandlerException::create(ErrorType::BadRequest, 'bad input');

        $handlerError = NexusFailureConverter::handlerExceptionToProto($e);

        self::assertSame(ErrorType::BadRequest->value, $handlerError->getErrorType());

        $failure = $handlerError->getFailure();
        self::assertNotNull($failure);
        self::assertSame('bad input', $failure->getMessage());

        $meta = \iterator_to_array($failure->getMetadata());
        self::assertSame(NexusFailureConverter::HANDLER_ERROR_TYPE, $meta['type']);

        $details = \json_decode($failure->getDetails(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame(ErrorType::BadRequest->value, $details[NexusFailureConverter::DETAILS_TYPE_KEY]);
        self::assertArrayHasKey(NexusFailureConverter::DETAILS_TRACEBACK_KEY, $details);
    }

    public function testHandlerExceptionRetryableOverrideSurfacesInDetails(): void
    {
        $e = HandlerException::create(
            ErrorType::Internal,
            'transient',
            retryBehavior: RetryBehavior::Retryable,
        );

        $handlerError = NexusFailureConverter::handlerExceptionToProto($e, includeTraceback: false);
        $details = \json_decode($handlerError->getFailure()->getDetails(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertTrue($details[NexusFailureConverter::DETAILS_RETRYABLE_OVERRIDE_KEY]);
    }

    public function testHandlerExceptionNonRetryableOverrideSurfacesInDetails(): void
    {
        $e = HandlerException::create(
            ErrorType::BadRequest,
            'never retry',
            retryBehavior: RetryBehavior::NonRetryable,
        );

        $handlerError = NexusFailureConverter::handlerExceptionToProto($e, includeTraceback: false);
        $details = \json_decode($handlerError->getFailure()->getDetails(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertFalse($details[NexusFailureConverter::DETAILS_RETRYABLE_OVERRIDE_KEY]);
    }

    public function testHandlerExceptionUnspecifiedRetryBehaviorOmitsOverride(): void
    {
        $e = HandlerException::create(ErrorType::Internal, 'no override');

        $handlerError = NexusFailureConverter::handlerExceptionToProto($e, includeTraceback: false);
        $details = \json_decode($handlerError->getFailure()->getDetails(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertArrayNotHasKey(NexusFailureConverter::DETAILS_RETRYABLE_OVERRIDE_KEY, $details);
    }

    public function testFlattenCauseChainPacksAllLevels(): void
    {
        $root = new \LogicException('root');
        $middle = new \RuntimeException('middle', previous: $root);
        $outer = new \Exception('outer', previous: $middle);

        $chain = NexusFailureConverter::flattenCauseChain($outer);

        self::assertCount(3, $chain);
        self::assertSame('outer', $chain[0]['message']);
        self::assertSame(\Exception::class, $chain[0]['type']);
        self::assertSame('middle', $chain[1]['message']);
        self::assertSame('root', $chain[2]['message']);
    }

    public function testFlattenCauseChainRespectsMaxDepth(): void
    {
        $deepest = new \Exception('level-5');
        $cursor = $deepest;
        for ($i = 4; $i >= 1; $i--) {
            $cursor = new \Exception("level-{$i}", previous: $cursor);
        }

        $chain = NexusFailureConverter::flattenCauseChain($cursor, maxDepth: 2);

        self::assertCount(2, $chain);
        self::assertSame('level-1', $chain[0]['message']);
        self::assertSame('level-2', $chain[1]['message']);
    }
}
