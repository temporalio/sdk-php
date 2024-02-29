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
use RoadRunner\Temporal\DTO\V1\Message;
use Temporal\Interceptor\Header;
use Temporal\Worker\Transport\Command\CommandInterface;
use Temporal\Worker\Transport\Command\FailureResponseInterface;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Worker\Transport\Command\SuccessResponseInterface;
use Temporal\Worker\Transport\Command\UpdateResponse;

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

    public function encode(CommandInterface $cmd): Message
    {
        $msg = new Message();

        switch (true) {
            case $cmd instanceof RequestInterface:
                $cmd->getPayloads()->setDataConverter($this->converter);
                $msg->setId($cmd->getID());

                $header = $cmd->getHeader();
                \assert($header instanceof Header);
                $header->setDataConverter($this->converter);

                $options = $cmd->getOptions();
                if ($options === []) {
                    $options = new \stdClass();
                }

                $msg->setCommand($cmd->getName());
                $msg->setOptions(\json_encode($options));
                $msg->setPayloads($cmd->getPayloads()->toPayloads());
                $msg->setHeader($header->toHeader());

                if ($cmd->getFailure() !== null) {
                    $msg->setFailure(FailureConverter::mapExceptionToFailure($cmd->getFailure(), $this->converter));
                }

                return $msg;

            case $cmd instanceof FailureResponseInterface:
                \is_int($cmd->getID()) and $msg->setId($cmd->getID());
                $msg->setFailure(FailureConverter::mapExceptionToFailure($cmd->getFailure(), $this->converter));

                return $msg;

            case $cmd instanceof SuccessResponseInterface:
                \is_int($cmd->getID()) and $msg->setId($cmd->getID());
                $cmd->getPayloads()->setDataConverter($this->converter);
                $msg->setPayloads($cmd->getPayloads()->toPayloads());

                return $msg;



            case $cmd instanceof UpdateResponse:
                $msg->setCommand($cmd->getCommand());
                $msg->setOptions(\json_encode($cmd->getOptions(), JSON_INVALID_UTF8_IGNORE | JSON_UNESCAPED_UNICODE));

                if ($cmd->getFailure() !== null) {
                    $msg->setFailure(FailureConverter::mapExceptionToFailure($cmd->getFailure(), $this->converter));
                }

                if ($cmd->getPayloads() !== null) {
                    $cmd->getPayloads()->setDataConverter($this->converter);
                    $msg->setPayloads($cmd->getPayloads()->toPayloads());
                }

                return $msg;

            default:
                throw new \InvalidArgumentException(\sprintf(self::ERROR_INVALID_COMMAND, \get_class($cmd)));
        }
    }
}
