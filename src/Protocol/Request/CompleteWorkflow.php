<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Protocol\Request;

use Temporal\Client\Protocol\Message\Request;
use Temporal\Client\Runtime\WorkflowContextInterface;

final class CompleteWorkflow extends Request
{
    /**
     * @var string
     */
    public const METHOD_NAME = 'CompleteWorkflow';

    /**
     * @var string
     */
    public const PARAM_RESULT = 'result';

    /**
     * @param WorkflowContextInterface $ctx
     * @param mixed $result
     */
    public function __construct(WorkflowContextInterface $ctx, $result)
    {
        parent::__construct(self::METHOD_NAME, [
            'rid'              => $ctx->getRunId(),
            self::PARAM_RESULT => $result,
        ]);
    }

    /**
     * @return mixed
     */
    public function getResult()
    {
        $params = $this->getParams();

        return $params[self::PARAM_RESULT] ?? null;
    }
}
