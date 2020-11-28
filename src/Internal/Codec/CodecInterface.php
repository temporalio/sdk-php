<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Codec;

use Temporal\Client\Exception\ProtocolException;
use Temporal\Client\Internal\Events\EventListenerInterface;
use Temporal\Client\Worker\Command\CommandInterface;

/**
 * @template-implements EventListenerInterface<CodecInterface::ON_*>
 */
interface CodecInterface extends EventListenerInterface
{
    /**
     * @var string
     */
    public const ON_ENCODING = 'encoding';

    /**
     * @var string
     */
    public const ON_ENCODED = 'encoded';

    /**
     * @var string
     */
    public const ON_DECODING = 'decoding';

    /**
     * @var string
     */
    public const ON_DECODED = 'decoded';

    /**
     * @param iterable<CommandInterface> $commands
     * @return string
     * @throws ProtocolException
     */
    public function encode(iterable $commands): string;

    /**
     * @param string $message
     * @return iterable<CommandInterface>
     * @throws ProtocolException
     */
    public function decode(string $message): iterable;
}
