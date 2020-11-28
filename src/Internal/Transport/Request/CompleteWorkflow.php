<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Transport\Request;

use Temporal\Client\Worker\Command\Request;

final class CompleteWorkflow extends Request
{
    /**
     * @var string
     */
    public const NAME = 'CompleteWorkflow';

    /**
     * @param mixed $result
     * @param array $requests
     */
    public function __construct($result, array $requests)
    {
        parent::__construct(self::NAME, [
            'result'         => [$result],
            'cancelRequests' => $requests,
        ]);
    }
}
