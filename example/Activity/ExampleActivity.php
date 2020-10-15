<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace App\Activity;

use Temporal\Client\Activity\ActivityMethod;

class ExampleActivity
{
    /**
     * @param array $arguments
     * @return array
     *
     * @ActivityMethod(name="ExampleActivity")
     */
    #[ActivityMethod(name: 'ExampleActivity')]
    public function handler(array $arguments = [])
    {
        return $arguments;
    }
}
