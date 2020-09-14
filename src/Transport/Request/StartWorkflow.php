<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Transport\Request;

final class StartWorkflow extends InputRequest
{
    /**
     * @var string
     */
    public const REQUEST_NAME = 'StartWorker';

    /**
     * @param mixed $payload
     */
    public function __construct($payload)
    {
        parent::__construct(self::REQUEST_NAME, $payload);

        $this->validate();
    }

    /**
     * @return void
     */
    protected function validate(): void
    {
        $this->matchOrFail('name', 'is_string');
        $this->matchOrFail('wid', 'is_string');
        $this->matchOrFail('rid', 'is_string');
    }
}
