<?php

/**
 * This file is part of Goridge package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\Goridge\Message;

class ProceedMessage extends Message implements ProceedMessageInterface
{
    /**
     * @var int
     */
    public $size;

    /**
     * @param string $body
     * @param int|null $size
     */
    public function __construct(string $body, int $size = null)
    {
        $this->size = $size ?? \strlen($body);

        parent::__construct($body);
    }
}
