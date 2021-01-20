<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\Declaration\Fixture\Interfaces;

use Temporal\Activity\ActivityMethod;
use Temporal\Common\MethodRetry;

interface SimpleWorkflowInterface
{
    /** @ActivityMethod(name="activityMethodFromInterface") */
    #[ActivityMethod(name: 'activityMethodFromInterface')]
    public function activityMethod(): void;
}
