<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Transport\Request;

final class CreateWorker extends Request
{
    /**
     * @var string
     */
    public const REQUEST_NAME = 'CreateWorker';

    /**
     * @param array $workflows
     * @param array $activities
     * @param string|null $taskQueue
     */
    public function __construct(array $workflows, array $activities, ?string $taskQueue = null)
    {
        $payload = [
            'taskQueue'  => $taskQueue,
            'activities' => $activities,
            'workflows'  => $workflows,
        ];

        parent::__construct(self::REQUEST_NAME, \array_filter($payload));
    }
}
