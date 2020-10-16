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
use Temporal\Client\Protocol\Command\RequestInterface;

class ExecuteActivity extends Request
{
    /**
     * @var string
     */
    public const NAME = 'ExecuteActivity';

    public function __construct(string $name, array $arguments = [], array $options = [])
    {
        parent::__construct(self::NAME, [
            'name' => $name,
        ]);
    }
}
