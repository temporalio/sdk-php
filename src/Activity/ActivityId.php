<?php

declare(strict_types=1);

namespace Temporal\Activity;

use Spiral\Attributes\NamedArgumentConstructor;

/**
 * Business level activity ID.
 *
 * This is not needed for most cases. If you have to specify this, talk to the Temporal team.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD), NamedArgumentConstructor]
final class ActivityId
{
    public string $id;

    public function __construct(string|array $id)
    {
        if (\is_array($id)) {
            $id = $id['value'] ?? $id['id'] ?? (\array_values($id)[0] ?? '');
        }
        $this->id = (string) $id;
    }
}
