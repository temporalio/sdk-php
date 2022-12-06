<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\DataConverter;

use Temporal\Api\Common\V1\Payloads;

/**
 * @psalm-import-type TypeEnum from Type
 */
interface HeaderInterface extends \Countable
{
    /**
     * Checks if any value present.
     */
    public function isEmpty(): bool;

    /**
     * @param array-key $index
     */
    public function getValue(int|string $index): string;

    /**
     * Returns collection of {@see Payloads}.
     *
     * @return iterable<string, Payloads>
     */
    public function toProtoCollection(): iterable;
}
