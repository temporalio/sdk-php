<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\DataConverter;

use Temporal\DataConverter\DataConverter;
use Temporal\Tests\Unit\UnitTestCase;

/**
 * @group data-converter
 */
class DataConverterTestCase extends UnitTestCase
{
    public function testConvert()
    {
        $dc = DataConverter::createDefault();

        $payload = $dc->toPayload('abc');
        $this->assertSame('abc', $dc->fromPayload($payload, 'string'));
    }
}
