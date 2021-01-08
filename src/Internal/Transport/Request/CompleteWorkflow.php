<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Transport\Request;

use Temporal\Worker\Command\ErrorResponse;
use Temporal\Worker\Command\Request;

final class CompleteWorkflow extends Request
{
    protected const NAME = 'CompleteWorkflow';
    protected const PAYLOAD_PARAMS = ['result'];

    /**
     * @param array $result
     * @param \Throwable|null $error
     */
    public function __construct(array $result, \Throwable $error = null)
    {
        if ($error instanceof \Throwable) {
            $error = ErrorResponse::exceptionToArray($error);
        }

        parent::__construct(
            self::NAME,
            [
                'result' => $result,
                'error' => $error
            ]
        );
    }
}
