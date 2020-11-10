<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Declaration;

use Temporal\Client\Activity\Meta\ActivityInterface;
use Temporal\Client\Activity\Meta\ActivityMethod;

/**
 * @template-implements HandledDeclarationInterface<ActivityInterface, ActivityMethod>
 */
interface ActivityDeclarationInterface extends HandledDeclarationInterface
{
    /**
     * @return ActivityInterface
     */
    public function getMetadata(): ActivityInterface;

    /**
     * @return ActivityMethod
     */
    public function getMethod(): ActivityMethod;
}
