<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Temporal\Workflow\OnConflictOptions;

#[CoversClass(OnConflictOptions::class)]
final class OnConflictOptionsTestCase extends TestCase
{
    public function testDefaultsAreAllTrue(): void
    {
        $options = new OnConflictOptions();

        $this->assertTrue($options->attachRequestId);
        $this->assertTrue($options->attachCompletionCallbacks);
        $this->assertTrue($options->attachLinks);
    }

    public function testAcceptsExplicitFlags(): void
    {
        $options = new OnConflictOptions(
            attachRequestId: false,
            attachCompletionCallbacks: true,
            attachLinks: false,
        );

        $this->assertFalse($options->attachRequestId);
        $this->assertTrue($options->attachCompletionCallbacks);
        $this->assertFalse($options->attachLinks);
    }
}
