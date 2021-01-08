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

final class ContinueAsNew extends Request
{
    protected const NAME = 'ContinueAsNew';

    /**
     * @param string $name
     * @param array $input
     */
    public function __construct(string $name, array $input)
    {
        parent::__construct(
            self::NAME,
            [
                'name' => $name,
                'args' => $input,
            ],
            $input
        );
    }
}
