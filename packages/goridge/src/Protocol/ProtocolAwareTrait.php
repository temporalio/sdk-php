<?php

/**
 * This file is part of Goridge package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\Goridge\Protocol;

/**
 * @mixin ProtocolAwareInterface
 */
trait ProtocolAwareTrait
{
    /**
     * @var ProtocolInterface
     */
    protected $protocol;

    /**
     * @return ProtocolInterface
     */
    public function getProtocol(): ProtocolInterface
    {
        return $this->protocol;
    }

    /**
     * @param ProtocolInterface $protocol
     * @return $this|ProtocolAwareInterface
     */
    public function over(ProtocolInterface $protocol): ProtocolAwareInterface
    {
        $this->protocol = $protocol;

        return $this;
    }
}
