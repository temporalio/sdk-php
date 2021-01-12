<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Workflow;

use React\Promise\PromiseInterface;
use Temporal\Activity\ActivityOptions;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\Payload;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Transport\ClientInterface;
use Temporal\Internal\Transport\Request\ExecuteActivity;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Workflow;
use Temporal\Workflow\ActivityStubInterface;

final class ActivityStub implements ActivityStubInterface
{
    private MarshallerInterface $marshaller;
    private ActivityOptions $options;

    /**
     * @param MarshallerInterface $marshaller
     * @param ActivityOptions $options
     */
    public function __construct(MarshallerInterface $marshaller, ActivityOptions $options)
    {
        $this->marshaller = $marshaller;
        $this->options = $options;
    }

    /**
     * @return ActivityOptions
     */
    public function getOptions(): ActivityOptions
    {
        return $this->options;
    }

    /**
     * @return array
     */
    public function getOptionsArray(): array
    {
        return $this->marshaller->marshal($this->getOptions());
    }

    /**
     * @param string $name
     * @param array $args
     * @param \ReflectionType|null $returnType
     * @return PromiseInterface
     */
    public function execute(string $name, array $args = [], \ReflectionType $returnType = null): PromiseInterface
    {
        $request = new ExecuteActivity(
            $name,
            EncodedValues::fromValues($args),
            $this->getOptionsArray()
        );

        return EncodedValues::decodePromise($this->request($request), $returnType);
    }

    /**
     * @param RequestInterface $request
     * @return PromiseInterface
     */
    protected function request(RequestInterface $request): PromiseInterface
    {
        /** @var Workflow\WorkflowContextInterface $context */
        $context = Workflow::getCurrentContext();

        return $context->request($request);
    }
}


