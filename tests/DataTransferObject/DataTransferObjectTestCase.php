<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Client\DataTransferObject;

use Temporal\Client\Internal\Support\DataTransferObject;
use Temporal\Tests\Client\TestCase;

abstract class DataTransferObjectTestCase extends TestCase
{
    /**
     * @return DataTransferObject
     */
    abstract protected function make(): DataTransferObject;
}
