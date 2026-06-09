<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus\Handler;

use Temporal\Nexus\Exception\InvalidArgumentException;
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
        foreach ($initial as $i => $link) {
            if (!$link instanceof Link) {
                throw new InvalidArgumentException(\sprintf(
                    'LinkCollection: initial[%s] must be a %s, got %s',
                    \is_int($i) ? (string) $i : \var_export($i, true),
                    Link::class,
                    \get_debug_type($link),
                ));
            }
        }
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
