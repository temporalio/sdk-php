<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit\Handler;

use Temporal\Nexus\Exception\InvalidArgumentException;
use Temporal\Nexus\Handler\OperationStartDetails;
use Temporal\Nexus\Link;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OperationStartDetails::class)]
final class OperationStartDetailsTest extends TestCase
{
    public function testStoresAllProperties(): void
    {
        $links = [new Link('https://x', 'app/x')];
        $d = new OperationStartDetails(
            requestId: 'req-1',
            callbackUrl: 'https://cb',
            callbackHeaders: ['Token' => 'secret'],
            links: $links,
        );

        self::assertSame('req-1', $d->requestId);
        self::assertSame('https://cb', $d->callbackUrl);
        self::assertSame(['Token' => 'secret'], $d->callbackHeaders);
        self::assertSame($links, $d->links);
    }

    public function testRejectsEmptyRequestId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('OperationStartDetails requires a non-empty requestId');
        new OperationStartDetails(requestId: '');
    }

    public function testRejectsNonLinkInLinksList(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('links[1] must be');
        /** @phpstan-ignore-next-line — intentionally wrong type */
        new OperationStartDetails(requestId: 'r', links: [new Link('a', 't'), 'not-a-link']);
    }

    public function testRejectsNonLinkInLinksMapWithStringKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("links['k']");
        /** @phpstan-ignore-next-line — intentionally wrong type */
        new OperationStartDetails(requestId: 'r', links: ['k' => 'not-a-link']);
    }
}
