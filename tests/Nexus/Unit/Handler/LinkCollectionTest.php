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
use Temporal\Nexus\Handler\LinkCollection;
use Temporal\Nexus\Link;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LinkCollection::class)]
final class LinkCollectionTest extends TestCase
{
    public function testRejectsNonLinkInInitial(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('initial[1] must be');
        /** @phpstan-ignore-next-line — intentionally wrong type */
        new LinkCollection([new Link('a', 't'), 'not-a-link']);
    }

    public function testRejectsNonLinkInInitialWithStringKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("initial['k']");
        /** @phpstan-ignore-next-line — intentionally wrong type */
        new LinkCollection(['k' => 'nope']);
    }


    public function testEmptyByDefault(): void
    {
        $c = new LinkCollection();

        self::assertSame([], $c->all());
    }

    public function testSeedListIsCopied(): void
    {
        $seed = [new Link('u', 't')];
        $c = new LinkCollection($seed);

        $seed[] = new Link('u2', 't2');

        self::assertCount(1, $c->all());
    }

    public function testAddAppendsInOrder(): void
    {
        $c = new LinkCollection();
        $c->add(new Link('a', 'x'));
        $c->add(new Link('b', 'y'), new Link('c', 'z'));

        $uris = \array_map(static fn(Link $l) => $l->uri, $c->all());
        self::assertSame(['a', 'b', 'c'], $uris);
    }
}
