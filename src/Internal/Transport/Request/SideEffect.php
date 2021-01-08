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

final class SideEffect extends Request
{
    protected const NAME = 'SideEffect';
    protected const PAYLOAD_PARAMS = ['result'];

    /**
     * @param mixed $value
     */
    public function __construct(...$value)
    {
        parent::__construct(self::NAME, [], $value);
    }
}
