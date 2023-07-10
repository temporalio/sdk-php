<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Activity;

use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;

#[ActivityInterface(prefix: "Php82.")]
class Php82TypesActivity
{
    #[ActivityMethod]
    public function returnNull(null $value): null
    {
        return $value;
    }

    #[ActivityMethod]
    public function returnTrue(true $value): true
    {
        return $value;
    }

    #[ActivityMethod]
    public function returnFalse(false $value): false
    {
        return $value;
    }
}
