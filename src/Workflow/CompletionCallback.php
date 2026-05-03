<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Workflow;

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
     * @param non-empty-string $url Callback URL the server invokes when the
     *        workflow reaches a terminal state.
     * @param array<string, string> $headers Optional headers sent verbatim.
     * @throws \InvalidArgumentException when $url is empty.
     */
    public function __construct(
        public readonly string $url,
        public readonly array $headers = [],
    ) {
        /** @psalm-suppress TypeDoesNotContainType — defensive runtime check */
        if ($url === '') {
            throw new \InvalidArgumentException('CompletionCallback: url must be a non-empty string');
        }
    }
}
