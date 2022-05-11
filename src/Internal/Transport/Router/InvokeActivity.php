<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Transport\Router;

use React\Promise\Deferred;
use Temporal\Activity;
use Temporal\Activity\ActivityInfo;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\DoNotCompleteOnResultException;
use Temporal\Internal\Activity\ActivityContext;
use Temporal\Internal\Declaration\Prototype\ActivityPrototype;
use Temporal\Internal\ServiceContainer;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Worker\Transport\RPCConnectionInterface;

class InvokeActivity extends Route
{
    /**
     * @var string
     */
    private const ERROR_NOT_FOUND = 'Activity with the specified name "%s" was not registered';

    private ServiceContainer $services;
    private RPCConnectionInterface $rpc;

    /**
     * @param ServiceContainer $services
     * @param RPCConnectionInterface $rpc
     */
    public function __construct(ServiceContainer $services, RPCConnectionInterface $rpc)
    {
        $this->rpc = $rpc;
        $this->services = $services;
    }

    /**
     * {@inheritDoc}
     */
    public function handle(RequestInterface $request, array $headers, Deferred $resolver): void
    {
        $options = $request->getOptions();
        $payloads = $request->getPayloads();
        $heartbeatDetails = null;

        // always in binary format
        $taskToken = $options['info']['TaskToken'] ?? '';
        $options['info']['TaskToken'] = \base64_decode($taskToken);

        if (($options['heartbeatDetails'] ?? 0) !== 0) {
            $offset = \count($payloads) - ($options['heartbeatDetails'] ?? 0);

            $heartbeatDetails = EncodedValues::sliceValues($this->services->dataConverter, $payloads, $offset);
            $payloads = EncodedValues::sliceValues($this->services->dataConverter, $payloads, 0, $offset);
        }

        $context = new ActivityContext($this->rpc, $this->services->dataConverter, $payloads, $heartbeatDetails);
        $context = $this->services->marshaller->unmarshal($options, $context);

        $prototype = $this->findDeclarationOrFail($context->getInfo());

        try {
            Activity::setCurrentContext($context);

            $handler = $prototype->getInstance()->getHandler();
            $result = $handler($payloads);

            if ($context->isDoNotCompleteOnReturn()) {
                $resolver->reject(DoNotCompleteOnResultException::create());
            } else {
                $resolver->resolve(EncodedValues::fromValues([$result]));
            }
        } catch (\Throwable $e) {
            $resolver->reject($e);
        } finally {
            $finalizer = $this->services->activities->getFinalizer();
            if ($finalizer !== null) {
                call_user_func($finalizer);
            }
            Activity::setCurrentContext(null);
        }
    }

    /**
     * @param ActivityInfo $info
     * @return ActivityPrototype
     */
    private function findDeclarationOrFail(ActivityInfo $info): ActivityPrototype
    {
        $activity = $this->services->activities->find($info->type->name);

        if ($activity === null) {
            throw new \LogicException(\sprintf(self::ERROR_NOT_FOUND, $info->type->name));
        }

        return $activity;
    }
}
