<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Workflow;

use Temporal\Client\Workflow\Context\InputInterface;
use Temporal\Client\Workflow\WorkflowInfo;

/**
 * @mixin InputInterface
 */
trait InputAwareTrait
{
    /**
     * @var InputInterface
     */
    protected InputInterface $input;

    /**
     * {@inheritDoc}
     */
    public function getInfo(): WorkflowInfo
    {
        return $this->input->getInfo();
    }

    /**
     * {@inheritDoc}
     */
    public function getArguments(): array
    {
        return $this->input->getArguments();
    }
}
