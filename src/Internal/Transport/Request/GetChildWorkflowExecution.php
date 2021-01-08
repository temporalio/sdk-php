<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Transport\Request;

use Temporal\Worker\Transport\Command\Request;

final class GetChildWorkflowExecution extends Request
{
    public const NAME = 'GetChildWorkflowExecution';

    /**
     * @param ExecuteChildWorkflow $execution
     */
    public function __construct(ExecuteChildWorkflow $execution)
    {
        parent::__construct(self::NAME, ['id' => $execution->getID()]);
    }
}
