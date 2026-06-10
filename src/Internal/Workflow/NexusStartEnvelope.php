<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Workflow;

use Temporal\Internal\Marshaller\Meta\Marshal;

/**
 * Nexus start-handshake outcome pushed by RoadRunner for {@see \Temporal\Internal\Transport\Request\GetNexusOperationStarted}: `$token` is set only when `$async` is true.
 *
 * @internal
 */
final class NexusStartEnvelope
{
    #[Marshal(name: 'async')]
    public bool $async = false;

    #[Marshal(name: 'token')]
    public string $token = '';
}
