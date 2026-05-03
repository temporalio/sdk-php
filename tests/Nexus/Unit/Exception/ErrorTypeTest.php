<?php

/**
 * This file is part of Nexus RPC SDK for PHP package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit\Exception;

use Temporal\Nexus\Exception\ErrorType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ErrorType::class)]
final class ErrorTypeTest extends TestCase
{
    /**
     * Spec-mandated wire values per SPEC.md "Predefined Handler Errors".
     */
    public function testWireValuesMatchSpec(): void
    {
        self::assertSame('BAD_REQUEST',        ErrorType::BadRequest->value);
        self::assertSame('UNAUTHENTICATED',    ErrorType::Unauthenticated->value);
        self::assertSame('UNAUTHORIZED',       ErrorType::Unauthorized->value);
        self::assertSame('NOT_FOUND',          ErrorType::NotFound->value);
        self::assertSame('REQUEST_TIMEOUT',    ErrorType::RequestTimeout->value);
        self::assertSame('CONFLICT',           ErrorType::Conflict->value);
        self::assertSame('RESOURCE_EXHAUSTED', ErrorType::ResourceExhausted->value);
        self::assertSame('INTERNAL',           ErrorType::Internal->value);
        self::assertSame('NOT_IMPLEMENTED',    ErrorType::NotImplemented->value);
        self::assertSame('UNAVAILABLE',        ErrorType::Unavailable->value);
        self::assertSame('UPSTREAM_TIMEOUT',   ErrorType::UpstreamTimeout->value);
    }

    /**
     * @return iterable<string, array{0: ErrorType, 1: int}>
     */
    public static function specStatusPairs(): iterable
    {
        yield 'BadRequest=400'        => [ErrorType::BadRequest,        400];
        yield 'Unauthenticated=401'   => [ErrorType::Unauthenticated,   401];
        yield 'Unauthorized=403'      => [ErrorType::Unauthorized,      403];
        yield 'NotFound=404'          => [ErrorType::NotFound,          404];
        yield 'RequestTimeout=408'    => [ErrorType::RequestTimeout,    408];
        yield 'Conflict=409'          => [ErrorType::Conflict,          409];
        yield 'ResourceExhausted=429' => [ErrorType::ResourceExhausted, 429];
        yield 'Internal=500'          => [ErrorType::Internal,          500];
        yield 'NotImplemented=501'    => [ErrorType::NotImplemented,    501];
        yield 'Unavailable=503'       => [ErrorType::Unavailable,       503];
        yield 'UpstreamTimeout=520'   => [ErrorType::UpstreamTimeout,   520];
    }

    #[DataProvider('specStatusPairs')]
    public function testHttpStatusMatchesSpec(ErrorType $type, int $expected): void
    {
        self::assertSame($expected, $type->httpStatus());
    }

    #[DataProvider('specStatusPairs')]
    public function testRoundTripThroughHttpStatus(ErrorType $type, int $status): void
    {
        self::assertSame($type, ErrorType::fromHttpStatus($status));
    }

    public function testUnknownMapsTo500(): void
    {
        self::assertSame(500, ErrorType::Unknown->httpStatus());
    }

    public function testFromHttpStatusUnknownCodeReturnsUnknown(): void
    {
        self::assertSame(ErrorType::Unknown, ErrorType::fromHttpStatus(418));
        self::assertSame(ErrorType::Unknown, ErrorType::fromHttpStatus(599));
        self::assertSame(ErrorType::Unknown, ErrorType::fromHttpStatus(200));
        self::assertSame(ErrorType::Unknown, ErrorType::fromHttpStatus(0));
    }

    public function testEveryCaseHasHttpStatus(): void
    {
        // Guard against new cases shipping without an httpStatus() arm.
        foreach (ErrorType::cases() as $case) {
            $status = $case->httpStatus();
            self::assertGreaterThanOrEqual(400, $status, "{$case->name} httpStatus");
            self::assertLessThan(600, $status, "{$case->name} httpStatus");
        }
    }
}
