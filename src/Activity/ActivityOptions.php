<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Activity;

final class ActivityOptions
{
    /**
     * The end to end timeout for the activity needed.
     *
     * @var \DateInterval|null
     */
    public ?\DateInterval $scheduleToCloseTimeout = null;

    /**
     * The queue timeout before the activity starts executed.
     *
     * @var \DateInterval|null
     */
    public ?\DateInterval $scheduleToStartTimeout = null;

    /**
     * The timeout from the start of execution to end of it.
     *
     * @var \DateInterval|null
     */
    public ?\DateInterval $startToCloseTimeout = null;

    /**
     * @return \DateInterval[]|null[]
     */
    public function toArray(): array
    {
        $result = [];

        if ($this->scheduleToCloseTimeout) {
            $result['scheduleToCloseTimeout'] = $this->scheduleToCloseTimeout->s;
        }

        if ($this->scheduleToStartTimeout) {
            $result['scheduleToStartTimeout'] = $this->scheduleToStartTimeout->s;
        }

        if ($this->startToCloseTimeout) {
            $result['startToCloseTimeout'] = $this->startToCloseTimeout->s;
        }

        return $result;
    }

    public function setScheduleToCloseTimeout($interval): void
    {
    }

    /**
     * @param mixed $options
     * @return static
     */
    public static function new($options): self
    {
        switch (true) {
            case $options === null:
                return new self();

            case \is_array($options):
                return self::fromArray($options);

            case $options instanceof self:
                return $options;

            default:
                throw new \InvalidArgumentException(\sprintf('Invalid %s type', self::class));
        }
    }

    /**
     * @param array $properties
     * @return static
     */
    public static function fromArray(array $properties): self
    {
        $instance = new self();

        foreach ($properties as $name => $value) {
            $setter = 'set' . \ucfirst($name);

            if (!\method_exists($instance, $setter)) {
                $error = \sprintf('Property %s not defined in %s', $name, self::class);
                throw new \InvalidArgumentException($error);
            }

            $instance->$setter($value);
        }

        return $instance;
    }
}
