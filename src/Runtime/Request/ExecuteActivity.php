<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Runtime\Request;

use Temporal\Client\Protocol\Message\Request;
use Temporal\Client\Runtime\WorkflowContextInterface;

final class ExecuteActivity extends Request
{
    /**
     * @var string
     */
    public const METHOD_NAME = 'ExecuteActivity';

    /**
     * @param WorkflowContextInterface $ctx
     * @param string $name
     * @param array $arguments
     */
    public function __construct(WorkflowContextInterface $ctx, string $name, array $arguments = [])
    {
        parent::__construct(self::METHOD_NAME, [
            'name'      => $name,
            'rid'       => $ctx->getRunId(),
            'arguments' => $arguments,
        ]);
    }
}
