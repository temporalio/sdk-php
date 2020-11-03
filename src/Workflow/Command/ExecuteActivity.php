<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Workflow\Command;

use Temporal\Client\Activity\ActivityOptions;
use Temporal\Client\Transport\Protocol\Command\Request;

class ExecuteActivity extends Request
{
    /**
     * @var string
     */
    public const NAME = 'ExecuteActivity';

    /**
     * @param string $name
     * @param array $arguments
     * @param ActivityOptions $options
     */
    public function __construct(string $name, array $arguments, ActivityOptions $options)
    {
        parent::__construct(self::NAME, [
            'name'      => $name,
            'arguments' => $arguments,
            'options'   => $options->toArray(),
        ]);
    }
}
