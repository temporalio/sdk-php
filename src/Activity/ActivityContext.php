<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Activity;

final class ActivityContext implements ActivityContextInterface
{
    /**
     * @var array
     */
    private array $params;

    /**
     * @param array $params
     */
    public function __construct(array $params)
    {
        $this->assertValid($params);

        $this->params = $params;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->params['name'];
    }

    /**
     * @return array
     */
    public function getArguments(): array
    {
        return $this->params['args'];
    }

    private function assertValid(array $params): void
    {
        // TODO
    }
}
