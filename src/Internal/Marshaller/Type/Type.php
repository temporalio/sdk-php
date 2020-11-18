<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Marshaller\Type;

use Temporal\Client\Internal\Marshaller\MarshallerInterface;

abstract class Type implements TypeInterface
{
    /**
     * @var MarshallerInterface
     */
    protected MarshallerInterface $marshaller;

    /**
     * @param MarshallerInterface $marshaller
     */
    public function __construct(MarshallerInterface $marshaller)
    {
        $this->marshaller = $marshaller;
    }
}
