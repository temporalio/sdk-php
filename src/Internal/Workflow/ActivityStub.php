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
use Temporal\DataConverter\Payload;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Transport\ClientInterface;
use Temporal\Internal\Transport\Request\ExecuteActivity;
use Temporal\Worker\Command\RequestInterface;
use Temporal\Workflow;
use Temporal\Workflow\ActivityStubInterface;

final class ActivityStub implements ActivityStubInterface, ClientInterface
{
    /**
     * @var DataConverterInterface
     */
    private DataConverterInterface $converter;

    /**
     * @var MarshallerInterface
     */
    private MarshallerInterface $marshaller;

    /**
     * @var ActivityOptions
     */
    private ActivityOptions $options;

    /**
     * @param DataConverterInterface $converter
     * @param MarshallerInterface $marshaller
     * @param ActivityOptions $options
     */
    public function __construct(
        DataConverterInterface $converter,
        MarshallerInterface $marshaller,
        ActivityOptions $options
    ) {
        $this->converter = $converter;
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
     * @param RequestInterface $request
     * @return PromiseInterface
     */
    public function request(RequestInterface $request): PromiseInterface
    {
        /** @var Workflow\WorkflowContextInterface $context */
        $context = Workflow::getCurrentContext();

        return $context->request($request);
    }

    /**
     * @param string $name
     * @param array $args
     * @param \ReflectionType|null $returnType
     * @return PromiseInterface
     */
    public function execute(string $name, array $args = [], \ReflectionType $returnType = null): PromiseInterface
    {
        $request = new ExecuteActivity($name, $args, $this->getOptionsArray());

        return Payload::fromPromise($this->converter, $this->request($request), $returnType);
    }
}


