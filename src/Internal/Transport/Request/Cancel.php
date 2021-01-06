<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Transport\Request;

use Temporal\Worker\Command\Request;

final class Cancel extends Request
{
    /**
     * @var string
     */
    public const NAME = 'Cancel';

    /**
     * @param ...int $requestId
     */
    public function __construct(int ...$requestId)
    {
        parent::__construct(
            self::NAME,
            [
                'ids' => $requestId,
            ]
        );
    }
}
