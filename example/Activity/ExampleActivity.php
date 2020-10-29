<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace App\Activity;

use Temporal\Client\Activity\Meta\ActivityMethod;

class ExampleActivity
{
    /** @ActivityMethod() */
    public function a($value)
    {
        sleep(1);
        return $value . ' from ' . __METHOD__;
    }

    /** @ActivityMethod() */
    public function b($value)
    {
        return strtolower($value) . ' from ' . __METHOD__;
    }
}
