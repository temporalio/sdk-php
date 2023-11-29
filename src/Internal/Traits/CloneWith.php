<?php

declare(strict_types=1);

namespace Temporal\Internal\Traits;

/**
 * @internal
 */
trait CloneWith
{
    /**
     * Return a new immutable instance with the specified property value.
     */
    private function with(string $key, mixed $value): static {
        $new = (new \ReflectionClass($this))->newInstanceWithoutConstructor();
        $new->{$key} = $value;
        foreach ($this as $k => $v) {
            if ($k === $key) {
                continue;
            }

            $new->{$k} = $v;
        }
        return $new;
    }
}
