<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Transport\Codec\ProtoCodec;

use Temporal\DataConverter\DataConverterInterface;
use Temporal\Exception\Failure\FailureConverter;
use Temporal\Roadrunner\Internal\Message;
use Temporal\Worker\Transport\Command\CommandInterface;
use Temporal\Worker\Transport\Command\FailureResponseInterface;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Worker\Transport\Command\SuccessResponseInterface;

/**
 * @codeCoverageIgnore tested via roadrunner-temporal repository.
 */
class Encoder
{
    /**
     * @var string
     */
    private const ERROR_INVALID_COMMAND = 'Unserializable command type %s';

    /**
     * @var DataConverterInterface
     */
    private DataConverterInterface $converter;

    /**
     * @param DataConverterInterface $converter
     */
    public function __construct(DataConverterInterface $converter)
    {
        $this->converter = $converter;
    }

    /**
     * @param CommandInterface $cmd
     * @return Message
     */
    public function encode(CommandInterface $cmd): Message
    {
        $msg = new Message();
        $msg->setId($cmd->getID());

        switch (true) {
            case $cmd instanceof RequestInterface:
                $cmd->getPayloads()->setDataConverter($this->converter);

                $options = $cmd->getOptions();
                if ($options === []) {
                    $options = new \stdClass();
                }

                $msg->setCommand($cmd->getName());
                $msg->setOptions(json_encode($options));
                $msg->setPayloads($cmd->getPayloads()->toPayloads());

                if ($cmd->getFailure() !== null) {
                    $msg->setFailure(FailureConverter::mapExceptionToFailure($cmd->getFailure(), $this->converter));
                }

                return $msg;

            case $cmd instanceof FailureResponseInterface:
                $msg->setFailure(FailureConverter::mapExceptionToFailure($cmd->getFailure(), $this->converter));

                return $msg;

            case $cmd instanceof SuccessResponseInterface:
                $cmd->getPayloads()->setDataConverter($this->converter);
                $msg->setPayloads($cmd->getPayloads()->toPayloads());

                return $msg;

            default:
                throw new \InvalidArgumentException(\sprintf(self::ERROR_INVALID_COMMAND, \get_class($cmd)));
        }
    }
}
