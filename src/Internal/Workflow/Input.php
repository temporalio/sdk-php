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
use Temporal\Client\Internal\Marshaller\Meta\Marshal;
use Temporal\Client\Workflow\Context\InputInterface;
use Temporal\Client\Workflow\WorkflowInfo;

#[Immutable]
final class Input implements InputInterface
{
    /**
     * @var WorkflowInfo
     */
    #[Marshal(name: 'info')]
    private WorkflowInfo $info;

    /**
     * @var array
     */
    #[Marshal(name: 'args')]
    private array $args;

    /**
     * @param WorkflowInfo $info
     * @param array $args
     */
    public function __construct(WorkflowInfo $info = null, array $args = [])
    {
        $this->info = $info ?? new WorkflowInfo();
        $this->args = $args;
    }

    /**
     * @return WorkflowInfo
     */
    public function getInfo(): WorkflowInfo
    {
        return $this->info;
    }

    /**
     * @return array
     */
    public function getArguments(): array
    {
        return $this->args;
    }
}
