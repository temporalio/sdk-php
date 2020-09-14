<?php

/**
 * This file is part of Goridge package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\Goridge\Protocol;

interface ProtocolAwareInterface
{
    /**
     * @return ProtocolInterface
     */
    public function getProtocol(): ProtocolInterface;

    /**
     * @param ProtocolInterface $protocol
     * @return $this
     */
    public function over(ProtocolInterface $protocol): self;
}
