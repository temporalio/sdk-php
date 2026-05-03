<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit\Handler;

use Temporal\Nexus\Handler\HandlerInputContent;
use Temporal\Nexus\Handler\HandlerResultContent;
use Temporal\Nexus\Handler\OperationContext;
use Temporal\Nexus\Internal\Headers;
use Temporal\Nexus\Serializer\Content;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Headers::class)]
#[CoversClass(OperationContext::class)]
#[CoversClass(HandlerInputContent::class)]
#[CoversClass(HandlerResultContent::class)]
#[CoversClass(Content::class)]
final class HeaderTest extends TestCase
{
    public function testOperationContextHeaders(): void
    {
        $context = new OperationContext(
            service: 'service',
            operation: 'operation',
            headers: [
                'UPPER-CASE-HEADER' => 'UPPER-VALUE',
                'lower-case-header' => 'lower-value',
            ],
        );

        $this->verifyHeaders($context->headers);
    }

    public function testHandlerResultContentHeaders(): void
    {
        $result = new HandlerResultContent(
            data: '',
            headers: [
                'UPPER-CASE-HEADER' => 'UPPER-VALUE',
                'lower-case-header' => 'lower-value',
            ],
        );

        $this->verifyHeaders($result->headers);
    }

    public function testContentHeaders(): void
    {
        $content = new Content(
            data: '',
            headers: [
                'UPPER-CASE-HEADER' => 'UPPER-VALUE',
                'lower-case-header' => 'lower-value',
            ],
        );

        $this->verifyHeaders($content->headers);
    }

    public function testHandlerInputHeaders(): void
    {
        $content = new HandlerInputContent(
            data: 'test',
            headers: [
                'UPPER-CASE-HEADER' => 'UPPER-VALUE',
                'lower-case-header' => 'lower-value',
            ],
        );

        $this->verifyHeaders($content->headers);
    }

    /**
     * @param array<string, string> $headers
     */
    private function verifyHeaders(array $headers): void
    {
        self::assertSame('UPPER-VALUE', $headers['upper-case-header']);
        self::assertSame('lower-value', $headers['lower-case-header']);

        // Verify all keys are lowercase
        foreach ($headers as $key => $value) {
            self::assertSame(\strtolower($key), $key);
        }
    }
}
