<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Transport\Codec;

use Temporal\Exception\ProtocolException;
use Temporal\Worker\Transport\Command\CommandInterface;
use Temporal\Worker\Transport\Command\ResponseInterface;
use Temporal\Worker\Transport\Command\ServerRequestInterface;

interface CodecInterface
{
    /**
     * @param iterable<CommandInterface> $commands
     * @return string
     * @throws ProtocolException
     */
    public function encode(iterable $commands): string;

    /**
     * @param string $batch
     * @return iterable<ServerRequestInterface|ResponseInterface>
     * @throws ProtocolException
     */
    public function decode(string $batch): iterable;
}
