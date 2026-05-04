<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Workflow;

use Temporal\Api\Common\V1\Link;
use Temporal\Internal\Nexus\NexusLinkConverter;
use Temporal\Nexus\Link as NexusLink;

/**
 * Public value object describing a Nexus-style completion callback registered
 * at workflow start. Decouples the caller-facing API from the
 * {@see \Temporal\Api\Common\V1\Callback} protobuf — the proto envelope is
 * built by {@see \Temporal\Internal\Client\WorkflowStarter} on the way out.
 *
 * @since Nexus support
 */
final class CompletionCallback
{
    /**
     * Proto Link[] attached to the Callback envelope on the wire. Populated via
     * {@see self::withNexusLinks()} from caller-supplied Nexus links; left empty
     * for non-Nexus completion callbacks.
     *
     * @var list<Link>
     */
    public readonly array $links;

    /**
     * @param non-empty-string $url Callback URL the server invokes when the
     *        workflow reaches a terminal state.
     * @param array<string, string> $headers Optional headers sent verbatim.
     * @param list<Link> $links Already-converted proto Links.
     * @throws \InvalidArgumentException when $url is empty.
     */
    public function __construct(
        public readonly string $url,
        public readonly array $headers = [],
        array $links = [],
    ) {
        /** @psalm-suppress TypeDoesNotContainType — defensive runtime check */
        if ($url === '') {
            throw new \InvalidArgumentException('CompletionCallback: url must be a non-empty string');
        }
        $this->links = $links;
    }

    /**
     * Build a callback with high-level Nexus links converted to proto Links.
     *
     * @param non-empty-string $url
     * @param array<string, string> $headers
     * @param iterable<NexusLink> $nexusLinks
     */
    public static function withNexusLinks(string $url, array $headers, iterable $nexusLinks): self
    {
        return new self($url, $headers, NexusLinkConverter::toProtoLinks($nexusLinks));
    }
}
