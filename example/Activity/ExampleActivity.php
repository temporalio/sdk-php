<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace App\Activity;

use Temporal\Client\Declaration\Activity;
use Temporal\Client\Declaration\ActivityInterface;

class ExampleActivity
{
    public function handler(array $arguments = [])
    {
        return $arguments;
    }

    /**
     * @return ActivityInterface
     */
    public static function toActivity(): ActivityInterface
    {
        $handler = [new static(), 'handler'];

        return new Activity('ExampleActivity', \Closure::fromCallable($handler));
    }
}
