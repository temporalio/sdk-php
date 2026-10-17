<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus\Handler;

use Temporal\Nexus\Link;

/**
 * Mutable. Created by transport, filled by handler/middleware, read by
 * transport after the handler returns.
 */
final class LinkCollection
{
    /** @var list<Link> */
    private array $links;

    /**
     * @param list<Link> $initial
     */
    public function __construct(array $initial = [])
    {
        Link::assertAll($initial, 'LinkCollection: initial');
        $this->links = \array_values($initial);
    }

    public function add(Link ...$links): void
    {
        foreach ($links as $link) {
            $this->links[] = $link;
        }
    }

    /**
     * @return list<Link>
     */
    public function all(): array
    {
        return $this->links;
    }
}
