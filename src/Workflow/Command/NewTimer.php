<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Workflow\Command;

use Temporal\Client\Protocol\Command\Request;

class NewTimer extends Request
{
    /**
     * @var string
     */
    public const NAME = 'NewTimer';

    /**
     * @param int $microseconds
     */
    public function __construct(int $microseconds)
    {
        parent::__construct(self::NAME, [
            'ms' => $microseconds,
        ]);
    }
}
