<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Worker\Env;

final class RoadRunner implements EnvironmentInterface
{
    /**
     * @var string
     */
    private const DEFAULT_MODE = 'temporal/unknown';

    /**
     * @var string
     */
    private const ENV_KEY = 'RR_MODE';

    /**
     * @var string
     */
    private string $mode;

    /**
     * @param string|null $mode
     */
    public function __construct(string $mode = null)
    {
        $this->mode = $mode ?? $_SERVER[self::ENV_KEY] ?? self::DEFAULT_MODE;
    }

    /**
     * @return string
     */
    public function get(): string
    {
        return $this->mode;
    }

    /**
     * @param string $type
     * @return bool
     */
    public function is(string $type): bool
    {
        return $this->mode === $type;
    }
}
