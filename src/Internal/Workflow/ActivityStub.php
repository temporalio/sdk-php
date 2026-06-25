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
use Temporal\DataConverter\ActivitySerializationContext;
use Temporal\DataConverter\EncodedValues;
use Temporal\Interceptor\Header;
use Temporal\Interceptor\HeaderInterface;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Transport\Request\ExecuteActivity;
use Temporal\Internal\Transport\Request\ExecuteLocalActivity;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Workflow;
use Temporal\Workflow\ActivityStubInterface;
use Temporal\DataConverter\Type;

final class ActivityStub implements ActivityStubInterface
{
    /** @var MarshallerInterface<array> */
    private MarshallerInterface $marshaller;

    private ActivityOptionsInterface $options;
    private HeaderInterface $header;

    /**
     * @param MarshallerInterface<array> $marshaller
     */
    public function __construct(
        MarshallerInterface $marshaller,
        ActivityOptionsInterface $options,
        HeaderInterface|array $header,
    ) {
        $this->marshaller = $marshaller;
        $this->options = $options;
        $this->header = \is_array($header) ? Header::fromValues($header) : $header;
    }

    public function getOptions(): ActivityOptionsInterface
    {
        return $this->options;
    }

    public function getOptionsArray(): array
    {
        return $this->marshaller->marshal($this->getOptions());
    }

    public function execute(
        string $name,
        array $args = [],
        Type|string|\ReflectionClass|\ReflectionType|null $returnType = null,
        bool $isLocalActivity = false,
    ): PromiseInterface {
        $info = Workflow::getCurrentContext()->getInfo();
        $taskQueue = $this->options instanceof ActivityOptions && $this->options->taskQueue !== null
            ? $this->options->taskQueue
            : $info->taskQueue;

        $arguments = EncodedValues::fromValues($args);
        $arguments->setSerializationContext(new ActivitySerializationContext(
            namespace: $info->namespace,
            workflowId: $info->execution->getID(),
            workflowType: $info->type->name,
            activityType: $name,
            taskQueue: $taskQueue,
            isLocal: $isLocalActivity,
        ));

        $request = $isLocalActivity ?
            new ExecuteLocalActivity($name, $arguments, $this->getOptionsArray(), $this->header) :
            new ExecuteActivity($name, $arguments, $this->getOptionsArray(), $this->header);

        return EncodedValues::decodePromise($this->request($request), $returnType);
    }

    protected function request(RequestInterface $request): PromiseInterface
    {
        return Workflow::getCurrentContext()->request($request);
    }
}
