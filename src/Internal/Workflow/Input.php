<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Workflow;

use JetBrains\PhpStorm\Immutable;
use Temporal\Client\DataConverter\Payload;
use Temporal\Client\Internal\Marshaller\Meta\Marshal;
use Temporal\Client\Internal\Marshaller\Meta\MarshalArray;
use Temporal\Client\Workflow\WorkflowInfo;

#[Immutable]
final class Input
{
    /**
     * @var WorkflowInfo
     */
    #[Marshal(name: 'info')]
    #[Immutable]
    public WorkflowInfo $info;

    /**
     * @var array
     */
    #[MarshalArray(name: 'args', of: Payload::class)]
    #[Immutable]
    public array $args;

    /**
     * @param WorkflowInfo $info
     * @param array<Payload> $args
     */
    public function __construct(WorkflowInfo $info = null, array $args = [])
    {
        $this->info = $info ?? new WorkflowInfo();
        $this->args = $args;
    }
}
