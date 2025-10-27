<?php

declare(strict_types=1);

namespace Temporal\Client\Schedule\Update;

use Temporal\Client\Schedule\Schedule;
use Temporal\DataConverter\EncodedCollection;
use Temporal\Internal\Marshaller\Meta\Marshal;
use Temporal\Internal\Traits\CloneWith;

/**
 * An update returned from a schedule updater.
 *
 * @see ScheduleHandle::update()
 */
final class ScheduleUpdate
{
    use CloneWith;

    /**
     * Search attributes to replace the existing search attributes with.
     */
    public readonly ?EncodedCollection $searchAttributes;

    /**
     * @param Schedule $schedule Schedule to replace the existing schedule with.
     */
    private function __construct(
        #[Marshal]
        public readonly Schedule $schedule,
    ) {
        $this->searchAttributes = null;
    }

    /**
     * @param Schedule $schedule Schedule to replace the existing schedule with.
     */
    public static function new(Schedule $schedule): self
    {
        return new self($schedule);
    }

    public function withSchedule(Schedule $schedule): self
    {
        /** @see self::$schedule */
        return $this->cloneWith('schedule', $schedule);
    }

    /**
     * @param ?EncodedCollection $searchAttributes Search attributes to replace the existing search attributes with.
     *        If null, it will not change the existing search attributes.
     */
    public function withSearchAttributes(?EncodedCollection $searchAttributes = null): self
    {
        /** @see self::$searchAttributes */
        return $this->cloneWith('searchAttributes', $searchAttributes);
    }
}
