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

final class OperationStartDetails
{
    /**
     * @param array<string, string> $callbackHeaders Headers to attach as-is on
     *        the async callback POST. Transport must strip the inbound
     *        `Nexus-Callback-` prefix first (see {@see \Temporal\Nexus\Header::CALLBACK_PREFIX}).
     * @param Link[] $links
     */
    public function __construct(
        public readonly string $requestId,
        public readonly ?string $callbackUrl = null,
        public readonly array $callbackHeaders = [],
        public readonly array $links = [],
    ) {
        if ($requestId === '') {
            throw new InvalidArgumentException('OperationStartDetails requires a non-empty requestId');
        }
        Link::assertAll($links, 'OperationStartDetails: links');
    }
}
