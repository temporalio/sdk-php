<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Framework\Expectation;

use PHPUnit\Framework\ExpectationFailedException;
use ReflectionClass;
use Temporal\DataConverter\EncodedValues;
use Temporal\Internal\Transport\Request\ExecuteActivity;
use Temporal\Worker\Transport\Command\Client\Response;
use Temporal\Worker\Transport\Command\CommandInterface;

/**
 * @internal
 */
final class ActivityCall implements ExpectationInterface
{
    private string $name;
    private array $values;

    public function __construct(string $activityClass, string $activityMethod, array $expectedValues)
    {
        $this->name = (new ReflectionClass($activityClass))->getShortName() . '.' . $activityMethod;
        $this->values = $expectedValues;
    }

    public function matches(CommandInterface $command): bool
    {
        return $command instanceof ExecuteActivity && $command->getOptions()['name'] === $this->name;
    }

    public function run(CommandInterface $command): CommandInterface
    {
        return Response::createSuccess(EncodedValues::fromValues($this->values), $command->getID());
    }

    public function fail(): void
    {
        throw new ExpectationFailedException(
            "Expected call of $this->name with " . implode(", ", $this->values) . "."
        );
    }
}
