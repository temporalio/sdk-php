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
use Temporal\Worker\Transport\Command\Server\TickInfo;

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

    public function getPayloads(): ValuesInterface;

    public function getHeader(): Header;

    public function getTickInfo(): TickInfo;
}
