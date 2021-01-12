<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Transport\Command;

use Temporal\DataConverter\Payload;

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
     * For incoming requests.
     *
     * @return array<Payload|mixed>
     * @todo: migrate to encoded values
     */
    public function getPayloads(): array;

    /**
     * Optional failure.
     *
     * @return \Throwable|null
     */
    public function getFailure(): ?\Throwable;

    /**
     * @return bool
     */
    public function isCancellable(): bool;
}
