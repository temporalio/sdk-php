<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Declaration;

final class Workflow extends HandledDeclaration implements WorkflowInterface
{
    /**
     * {@inheritDoc}
     */
    public function getQueryHandlers(): iterable
    {
        throw new \LogicException(__METHOD__ . ' not implemented yet');
    }

    /**
     * {@inheritDoc}
     */
    public function getSignalHandlers(): iterable
    {
        throw new \LogicException(__METHOD__ . ' not implemented yet');
    }
}
