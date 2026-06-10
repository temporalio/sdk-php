<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit\Exception;

use Temporal\Nexus\Exception\ErrorType;
use PHPUnit\Framework\Attributes\CoversClass;
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
}
