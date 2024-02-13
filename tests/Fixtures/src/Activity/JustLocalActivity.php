<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Activity;

use Temporal\Activity;

#[Activity\LocalActivityInterface(prefix: "JustLocalActivity.")]
class JustLocalActivity
{
    #[Activity\ActivityMethod]
    public function echo(
        string $input
    ): string {
        return strtoupper($input);
    }
}
