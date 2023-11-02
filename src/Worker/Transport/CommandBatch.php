<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Transport;

/**
 * Carries multiple commands between host and worker process (use proper codec to read command content).
 */
final class CommandBatch
{
    public string $messages;
    public array $context;

    /**
     * @param string $messages
     * @param array $context
     */
    public function __construct(string $messages, array $context)
    {
        $this->messages = $messages;
        $this->context = $context;
    }
}
