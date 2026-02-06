<?php

declare(strict_types=1);

namespace Temporal\Activity;

use Attribute;

/**
 * Optional summary of the activity.
 *
 * Single-line fixed summary for this activity that will appear in UI/CLI.
 * This can be in single-line Temporal Markdown format.
 *
 * @experimental This API is experimental and may change in the future.
 *
 * @since RoadRunner 2025.1.2
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class Summary
{
    public string $text;

    /**
     * @param string|array $text
     */
    public function __construct(string|array $text)
    {
        if (\is_array($text)) {
            $text = $text['value'] ?? $text['text'] ?? (array_values($text)[0] ?? '');
        }
        $this->text = (string) $text;
    }
}
