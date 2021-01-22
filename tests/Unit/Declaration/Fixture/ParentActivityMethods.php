<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\Declaration\Fixture;

use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Tests\Unit\Declaration\Fixture\Interfaces\SimpleActivityInterface;
use Temporal\Tests\Unit\Declaration\Fixture\Interfaces\SimpleWorkflowInterface;

/** @ActivityInterface(prefix="prefix.") */
#[ActivityInterface(prefix: "prefix.")]
abstract class ParentActivityMethods implements SimpleActivityInterface
{
    /** @ActivityMethod(name="alternativeActivityName") */
    #[ActivityMethod(name: 'alternativeActivityName')]
    public function activityMethod(): void
    {
    }
}
