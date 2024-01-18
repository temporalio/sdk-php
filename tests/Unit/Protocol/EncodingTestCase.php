<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\Protocol;

use PHPUnit\Framework\Attributes\Test;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\EncodedValues;

/**
 * @group unit
 * @group protocol
 */
class EncodingTestCase extends AbstractProtocol
{
    #[Test]
    public function nullValuesAreReturned(): void
    {
        $encodedValues = EncodedValues::fromValues([null, 'something'], new DataConverter());
        $this->assertNull($encodedValues->getValue(0));
    }
}
