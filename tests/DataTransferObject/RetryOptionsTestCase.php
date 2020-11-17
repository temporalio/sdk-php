<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Client\DataTransferObject;

use Temporal\Client\Common\RetryOptions;
use Temporal\Client\Internal\Support\DataTransferObject;

class RetryOptionsTestCase extends DataTransferObjectTestCase
{
    /**
     * @return DataTransferObject
     */
    protected function make(): DataTransferObject
    {
        return new RetryOptions();
    }
}
