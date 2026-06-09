<?php

declare(strict_types=1);

namespace Temporal\Testing\Transcript;

final class MalformedTranscriptException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $offendingLine,
        public readonly int $offendingLineNumber,
        public readonly string $offendingFile,
    ) {
        parent::__construct(\sprintf(
            '%s (file=%s line=%d offending=%s)',
            $message,
            $offendingFile,
            $offendingLineNumber,
            \substr($offendingLine, 0, 200),
        ));
    }
}
