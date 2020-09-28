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

class StartWorker extends Request
{
    /**
     * @var string
     */
    public const METHOD_NAME = 'StartWorker';

    /**
     * @param string $name
     */
    public function __construct(string $name)
    {
        parent::__construct(self::METHOD_NAME, [
            'name' => $name,
        ]);
    }
}
