<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Common;

/**
 * @psalm-type IdReusePolicyEnum = IdReusePolicy::POLICY_*
 */
final class IdReusePolicy
{
    /**
     * @var int
     */
    public const POLICY_UNSPECIFIED = 0;

    /**
     * Allow start a workflow execution using the same workflow Id, when
     * workflow not running.
     */
    public const POLICY_ALLOW_DUPLICATE = 1;

    /**
     * Allow start a workflow execution using the same workflow Id, when
     * workflow not running, and the last execution close state is in
     * [terminated, cancelled, timed out, failed].
     */
    public const POLICY_ALLOW_DUPLICATE_FAILED_ONLY = 2;

    /**
     * Do not allow start a workflow execution using the same workflow
     * Id at all.
     */
    public const POLICY_REJECT_DUPLICATE = 3;
}
