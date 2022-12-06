<?php

declare(strict_types=1);

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\DataConverter;

/**
 * @extends EncodedPayloads<array-key, string>
 */
class EncodedHeader extends EncodedPayloads implements HeaderInterface
{
    public function getValue(int|string $index): string
    {
        return parent::getValue($index);
    }
}
