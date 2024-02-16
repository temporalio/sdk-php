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
use Temporal\Interceptor\Header;

/**
 * @psalm-import-type RequestOptions from RequestInterface
 */
interface ServerRequestInterface extends CommandInterface
{
    public function getID(): string;

    /**
     * @return non-empty-string
     */
    public function getName(): string;

    /**
     * @return RequestOptions
     */
    public function getOptions(): array;

    /**
     * @return ValuesInterface
     */
    public function getPayloads(): ValuesInterface;

    /**
     * @return int<0, max>
     */
    public function getHistoryLength(): int;

    /**
     * @return Header
     */
    public function getHeader(): Header;
}
