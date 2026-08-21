<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Client;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Temporal\Internal\Client\OnConflictOptions;

#[CoversClass(OnConflictOptions::class)]
final class OnConflictOptionsTestCase extends TestCase
{
    public function testDefaultsAreAllTrue(): void
    {
        $options = new OnConflictOptions();

        self::assertTrue($options->attachRequestId);
        self::assertTrue($options->attachCompletionCallbacks);
        self::assertTrue($options->attachLinks);
    }

    public function testAcceptsExplicitFlags(): void
    {
        $options = new OnConflictOptions(
            attachRequestId: false,
            attachCompletionCallbacks: true,
            attachLinks: false,
        );

        self::assertFalse($options->attachRequestId);
        self::assertTrue($options->attachCompletionCallbacks);
        self::assertFalse($options->attachLinks);
    }

    public function testForNexusCompletionCallbackHardcodesAllTrue(): void
    {
        $options = new OnConflictOptions();

        self::assertTrue($options->attachRequestId);
        self::assertTrue($options->attachCompletionCallbacks);
        self::assertTrue($options->attachLinks);
    }
}
