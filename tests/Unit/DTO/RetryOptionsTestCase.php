<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\DTO;

use Temporal\Common\RetryOptions;

class RetryOptionsTestCase extends AbstractDTOMarshalling
{
    /**
     * @throws \ReflectionException
     */
    public function testMarshalling(): void
    {
        $dto = new RetryOptions();

        $expected = [
            'initial_interval' => null,
            'backoff_coefficient' => 2.0,
            'maximum_interval' => null,
            'maximum_attempts' => 0,
            'non_retryable_error_types' => [],
        ];

        $this->assertSame($expected, $this->marshal($dto));
    }
}
