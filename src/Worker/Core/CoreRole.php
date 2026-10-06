<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Core;

use Temporal\Internal\Bridge\CoreEnvironment;

/**
 * @internal
 */
enum CoreRole: string
{
    case All = 'all';
    case Workflow = 'workflow';
    case Activity = 'activity';

    public static function fromEnvironment(): ?self
    {
        $role = CoreEnvironment::string(CoreEnvironment::ROLE);

        return $role === null ? null : self::from($role);
    }

    public function runsWorkflows(): bool
    {
        return $this !== self::Activity;
    }

    public function runsRemoteActivities(): bool
    {
        return $this !== self::Workflow;
    }
}
