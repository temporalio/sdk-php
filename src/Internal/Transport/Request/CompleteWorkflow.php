<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Transport\Request;

use Temporal\Worker\Transport\Command\ErrorResponse;
use Temporal\Worker\Transport\Command\Request;

final class CompleteWorkflow extends Request
{
    protected const NAME = 'CompleteWorkflow';

    /**
     * @param array $result
     * @param \Throwable|null $error
     */
    public function __construct(array $result, \Throwable $error = null)
    {
        if ($error instanceof \Throwable) {
            $error = ErrorResponse::exceptionToArray($error);
        }

        parent::__construct(self::NAME, ['error' => $error], $result);
    }
}
