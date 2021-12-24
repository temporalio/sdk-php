<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Framework\Expectation;

use Temporal\Internal\Transport\Request\CompleteWorkflow;
use Temporal\Worker\Transport\Command\CommandInterface;
use Temporal\Worker\Transport\Command\SuccessResponse;

use function PHPUnit\Framework\assertSame;

/**
 * @internal
 */
final class WorkflowResult implements ExpectationInterface
{
    private $expectedResult;
    private $actualResult;

    public function __construct($expectedResult)
    {
        $this->expectedResult = $expectedResult;
    }

    public function matches(CommandInterface $command): bool
    {
        if (!$command instanceof CompleteWorkflow) {
            return false;
        }
        $this->actualResult = $command->getPayloads()->getValue(0, null);

        return $this->actualResult === $this->expectedResult;
    }

    public function run(CommandInterface $command): CommandInterface
    {
        return new SuccessResponse(null, $command->getID());
    }

    public function fail(): void
    {
        assertSame($this->expectedResult, $this->actualResult, 'Wrong workflow result.');
    }
}
