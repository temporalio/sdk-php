<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Workflow;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Temporal\Workflow;

class NonReplayingLogger implements LoggerInterface
{
    use LoggerTrait;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $parent;

    /**
     * @param LoggerInterface $parent
     */
    private function __construct(LoggerInterface $parent)
    {
        $this->parent = $parent;
    }

    /**
     * @param LoggerInterface $parent
     * @return NonReplayingLogger
     */
    public static function wrapLogger(LoggerInterface $parent)
    {
        return new self($parent);
    }

    /**
     * Only sends log message when Workflow in not replaying.
     *
     * @param mixed $level
     * @param string $message
     * @param array $context
     */
    public function log($level, $message, array $context = array())
    {
        if (Workflow::isReplaying()) {
            return;
        }

        $this->parent->log($level, $message, $context);
    }
}
