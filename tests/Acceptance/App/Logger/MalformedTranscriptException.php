<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Logger;

final class MalformedTranscriptException extends \RuntimeException
{
    public readonly string $offendingLine;

    public readonly int $offendingLineNumber;

    public readonly string $offendingFile;

    public function __construct(
        string $message,
        string $offendingLine,
        int $lineNumber,
        string $file,
    ) {
        $this->offendingLine = $offendingLine;
        $this->offendingLineNumber = $lineNumber;
        $this->offendingFile = $file;
        parent::__construct(\sprintf(
            '%s (file=%s line=%d offending=%s)',
            $message,
            $file,
            $lineNumber,
            \substr($offendingLine, 0, 200),
        ));
    }
}
