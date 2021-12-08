<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Framework\Assertion;

use Temporal\Internal\Transport\Request\CompleteWorkflow;
use Temporal\Worker\Transport\Command\CommandInterface;

use function PHPUnit\Framework\assertSame;

/**
 * @internal
 */
final class WorkflowResult implements AssertionInterface
{
    /** @var mixed */
    private $result;
    private const MESSAGE = 'Wrong workflow result.';

    public function __construct($result)
    {
        $this->result = $result;
    }

    public function matches(CommandInterface $command): bool
    {
        return $command instanceof CompleteWorkflow;
    }

    public function assert(CommandInterface $command): void
    {
        $result = $command->getPayloads()->getValue(0, null);
        assertSame($result, $this->result, self::MESSAGE);
    }
}
