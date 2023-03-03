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
use Temporal\Interceptor\ActivityInboundInterceptor;
use Temporal\Interceptor\WorkflowClient\ActivityInput;
use Temporal\Internal\Activity\ActivityContext;
use Temporal\Internal\Declaration\Prototype\ActivityPrototype;
use Temporal\Internal\Interceptor\PipelineProvider;
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
    private PipelineProvider $interceptorProvider;

    /**
     * @param ServiceContainer $services
     * @param RPCConnectionInterface $rpc
     * @param PipelineProvider $interceptorProvider
     */
    public function __construct(
        ServiceContainer $services,
        RPCConnectionInterface $rpc,
        PipelineProvider $interceptorProvider,
    ) {
        $this->rpc = $rpc;
        $this->services = $services;
        $this->interceptorProvider = $interceptorProvider;
    }

    /**
     * {@inheritDoc}
     */
    public function handle(RequestInterface $request, array $headers, Deferred $resolver): void
    {
        $options = $request->getOptions();
        $payloads = $request->getPayloads();
        $header = $request->getHeader();
        $heartbeatDetails = null;

        // always in binary format
        $taskToken = $options['info']['TaskToken'] ?? '';
        $options['info']['TaskToken'] = \base64_decode($taskToken);

        if (($options['heartbeatDetails'] ?? 0) !== 0) {
            $offset = \count($payloads) - ($options['heartbeatDetails'] ?? 0);

            $heartbeatDetails = EncodedValues::sliceValues($this->services->dataConverter, $payloads, $offset);
            $payloads = EncodedValues::sliceValues($this->services->dataConverter, $payloads, 0, $offset);
        }

        $context = new ActivityContext(
            $this->rpc,
            $this->services->dataConverter,
            $payloads,
            $header,
            $heartbeatDetails,
        );
        /** @var ActivityContext $context */
        $context = $this->services->marshaller->unmarshal($options, $context);

        $prototype = $this->findDeclarationOrFail($context->getInfo());

        try {
            $handler = $prototype->getInstance()->getHandler();

            // Run Activity in an interceptors pipeline
            $result = $this->interceptorProvider
                ->getPipeline(ActivityInboundInterceptor::class)
                ->with(
                    static function (ActivityInput $input) use ($handler, $context): mixed {
                        Activity::setCurrentContext(
                            $context->withInput($input->arguments)->withHeader($input->header),
                        );
                        return $handler($input->arguments);
                    },
                    /** @see ActivityInboundInterceptor::handleActivityInbound() */
                    'handleActivityInbound',
                )(new ActivityInput($context->getInput(), $context->getHeader()));

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
                \call_user_func($finalizer);
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
