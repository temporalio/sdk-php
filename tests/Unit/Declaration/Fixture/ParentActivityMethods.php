<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\Declaration\Fixture;

use Temporal\Activity\ActivityMethod;
use Temporal\Tests\Unit\Declaration\Fixture\Interfaces\SimpleWorkflowInterface;

abstract class ParentActivityMethods implements SimpleWorkflowInterface
{
    /** @ActivityMethod(name="AlternativeActivityName") */
    #[ActivityMethod(name: 'AlternativeActivityName')]
    public function activityMethod(): void
    {
    }

    public function activityMethodFromParentClass(): void
    {
    }
}
