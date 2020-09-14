<?php

/**
 * This file is part of Goridge package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\Goridge;

interface ReceiverInterface
{
    /**
     * @param \Closure $onMessage
     */
    public function onMessage(\Closure $onMessage): void;

    /**
     * @param \Closure $onError
     */
    public function onError(\Closure $onError): void;

    /**
     * @param \Closure $onCommand
     */
    public function onCommand(\Closure $onCommand): void;
}
