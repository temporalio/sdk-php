<?php

declare(strict_types=1);

namespace Temporal\Client\Schedule;

use Temporal\DataConverter\EncodedCollection;
use Temporal\Internal\Traits\CloneWith;

/**
 * Options for creating a Schedule.
 *
 * @see \Temporal\Client\ScheduleClientInterface::createSchedule()
 */
final class ScheduleOptions
{
    use CloneWith;

    public readonly string $namespace;

    public readonly bool $triggerImmediately;

    /**
     * @var list<BackfillPeriod>
     */
    public readonly array $backfills;

    public readonly EncodedCollection $memo;

    public readonly EncodedCollection $searchAttributes;

    private function __construct()
    {
        $this->namespace = 'default';
        $this->triggerImmediately = false;
        $this->backfills = [];
        $this->memo = EncodedCollection::empty();
        $this->searchAttributes = EncodedCollection::empty();
    }

    public static function new(): self
    {
        return new self();
    }

    public function withNamespace(string $namespace): self
    {
        /** @see self::$namespace */
        return $this->with('namespace', $namespace);
    }

    /**
     * Trigger one Action immediately on creating the Schedule.
     */
    public function withTriggerImmediately(bool $value): self
    {
        /** @see self::$triggerImmediately */
        return $this->with('triggerImmediately', $value);
    }

    /**
     * Returns a new instance with the replaced backfill list.
     *
     * Runs though the specified time periods and takes Actions as if that time passed by right now, all at once.
     * The overlap policy can be overridden for the scope of the Schedule Backfill.
     */
    public function withBackfills(BackfillPeriod ...$values): self
    {
        /** @see self::$backfills */
        return $this->with('backfills', $values);
    }

    /**
     * Adds a new backfill period to the list.
     *
     * Runs though the specified time periods and takes Actions as if that time passed by right now, all at once.
     * The overlap policy can be overridden for the scope of the Schedule Backfill.
     */
    public function withAddedBackfill(BackfillPeriod $value): self
    {
        /** @see self::$backfills */
        return $this->with('backfills', [...$this->backfills, $value]);
    }

    /**
     * Optional non-indexed info that will be shown in list schedules.
     *
     * @param iterable<non-empty-string, mixed>|EncodedCollection $values
     */
    public function withMemo(iterable|EncodedCollection $values): self
    {
        $values instanceof EncodedCollection or $values = EncodedCollection::fromValues($values);

        /** @see self::$memo */
        return $this->with('memo', $values);
    }

    /**
     * Optional indexed info that can be used in query of List schedules APIs.
     * The key and value type must be registered on Temporal server side. Use GetSearchAttributes API
     * to get valid key and corresponding value type. For supported operations on different server
     * versions see {@link https://docs.temporal.io/visibility}.
     *
     * @param iterable<non-empty-string, mixed>|EncodedCollection $values
     */
    public function withSearchAttributes(iterable|EncodedCollection $values): self
    {
        $values instanceof EncodedCollection or $values = EncodedCollection::fromValues($values);

        /** @see self::$searchAttributes */
        return $this->with('searchAttributes', $values);
    }
}
