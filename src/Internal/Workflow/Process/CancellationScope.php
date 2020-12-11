<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Workflow\Process;

use JetBrains\PhpStorm\Pure;
use Temporal\Client\Common\Uuid;
use Temporal\Client\Internal\ServiceContainer;
use Temporal\Client\Workflow\WorkflowContext;

/**
 * @internal CancellationScope is an internal library class, please do not use it in your code.
 * @psalm-internal Temporal\Client
 */
class CancellationScope extends Scope
{
    /**
     * @var string
     */
    private string $id;

    /**
     * {@inheritDoc}
     */
    public function __construct(
        WorkflowContext $ctx,
        ServiceContainer $services,
        callable $handler,
        array $args = []
    ) {
        $this->id = Uuid::v4();

        parent::__construct($ctx, $services, $handler, $args);
    }

    /**
     * @return string
     */
    #[Pure]
    public function getRunId(): string
    {
        return $this->context->getRunId();
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @param mixed $result
     */
    protected function onComplete($result): void
    {
        //
    }
}
