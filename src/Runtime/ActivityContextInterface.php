<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Runtime;

use Temporal\Client\Transport\Request\InputRequestInterface;

interface ActivityContextInterface
{
    /**
     * @return InputRequestInterface
     */
    public function getRequest(): InputRequestInterface;
}
