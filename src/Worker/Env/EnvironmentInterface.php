<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Worker\Env;

interface EnvironmentInterface
{
    /**
     * @var string
     */
    public const ENV_ACTIVITY = 'temporal/activity';

    /**
     * @var string
     */
    public const ENV_WORKFLOW = 'temporal/workflow';

    /**
     * @return string
     */
    public function get(): string;

    /**
     * @param string $type
     * @return bool
     */
    public function is(string $type): bool;
}
