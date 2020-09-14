<?php

/**
 * This file is part of Goridge package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\Goridge;

interface ResponderInterface
{
    /**
     * @param string $body
     * @param int $flags
     */
    public function send(string $body, int $flags): void;

    /**
     * @param string $message
     * @param int $flags
     */
    public function throw(string $message, int $flags): void;

    /**
     * @param string $command
     * @param int $flags
     */
    public function exec(string $command, int $flags): void;
}
