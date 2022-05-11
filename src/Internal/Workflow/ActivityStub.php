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
use Temporal\Activity\ActivityOptionsInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\Internal\Declaration\Prototype\ActivityPrototype;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Transport\Request\ExecuteActivity;
use Temporal\Internal\Transport\Request\ExecuteLocalActivity;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Workflow;
use Temporal\Workflow\ActivityStubInterface;

final class ActivityStub implements ActivityStubInterface
{
    private MarshallerInterface $marshaller;
    private ActivityOptionsInterface $options;

    /**
     * @param MarshallerInterface $marshaller
     * @param ActivityOptionsInterface $options
     */
    public function __construct(MarshallerInterface $marshaller, ActivityOptionsInterface $options)
    {
        $this->marshaller = $marshaller;
        $this->options = $options;
    }

    /**
     * {@inheritDoc}
     */
    public function getOptions(): ActivityOptionsInterface
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
     * {@inheritDoc}
     */
    public function execute(string $name, array $args = [], $returnType = null, bool $isLocalActivity = false): PromiseInterface
    {
        $request = $isLocalActivity ?
            new ExecuteLocalActivity($name, EncodedValues::fromValues($args), $this->getOptionsArray()) :
            new ExecuteActivity($name, EncodedValues::fromValues($args), $this->getOptionsArray());

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
