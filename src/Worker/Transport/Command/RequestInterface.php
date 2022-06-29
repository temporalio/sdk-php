<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Transport\Command;

use Temporal\DataConverter\ValuesInterface;

interface RequestInterface extends CommandInterface
{
    /**
     * @return string
     */
    public function getName(): string;

    /**
     * @return array
     */
    public function getOptions(): array;

    /**
     * @return ValuesInterface
     */
    public function getPayloads(): ValuesInterface;

    /**
     * Optional failure.
     *
     * @return \Throwable|null
     */
    public function getFailure(): ?\Throwable;

    public function shouldBeWaitedFor(): bool;
}
