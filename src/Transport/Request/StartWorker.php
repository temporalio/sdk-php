<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Transport\Request;

final class StartWorker extends Request
{
    /**
     * @var string
     */
    public const REQUEST_NAME = 'StartWorker';

    public function __construct()
    {
        parent::__construct(self::REQUEST_NAME, null);
    }
}
