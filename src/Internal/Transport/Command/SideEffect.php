<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Transport\Command;

use Temporal\Client\Internal\Transport\Protocol\Command\Request;

final class SideEffect extends Request
{
    /**
     * @var string
     */
    public const NAME = 'SideEffect';

    /**
     * @param mixed $value
     */
    public function __construct($value)
    {
        parent::__construct(self::NAME, [
            'value' => $value,
        ]);
    }
}
