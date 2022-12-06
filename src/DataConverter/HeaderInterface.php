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
     *
     * @return bool
     */
    public function isEmpty(): bool;

    /**
     * @param DataConverterInterface $converter
     */
    public function setDataConverter(DataConverterInterface $converter);

    /**
     * @param Type|TypeEnum|mixed $type
     * @return mixed
     */
    public function getValue(string $index, $type): mixed;

    /**
     * Returns collection of {@see Payloads}.
     *
     * @return iterable<string, Payloads>
     */
    public function toProtoCollection(): iterable;
}
