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
    public function doSomething($value)
    {
        return 42;
    }

    /** @ActivityMethod() */
    public function doSomethingElse()
    {
        return 42;
    }
}
