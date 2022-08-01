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
use Temporal\Workflow\ParentClosePolicy;

final class GetChildWorkflowExecution extends Request
{
    public const NAME = 'GetChildWorkflowExecution';
    /** @see ParentClosePolicy */
    private int $parentClosePolicy;

    /**
     * @param ExecuteChildWorkflow $execution
     */
    public function __construct(ExecuteChildWorkflow $execution)
    {
        $this->parentClosePolicy = $execution->getOptions()['options']['ParentClosePolicy'] ?? ParentClosePolicy::POLICY_UNSPECIFIED;
        parent::__construct(self::NAME, ['id' => $execution->getID()]);
    }
}
