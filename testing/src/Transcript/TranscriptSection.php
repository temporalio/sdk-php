<?php

declare(strict_types=1);

namespace Temporal\Testing\Transcript;

enum TranscriptSection: string
{
    case TEST_START = 'TEST_START';
    case TEST_END = 'TEST_END';
    case LOG = 'LOG';
    case WIRE_INBOUND = 'WIRE_INBOUND';
    case WIRE_OUTBOUND = 'WIRE_OUTBOUND';
    case WIRE_ERROR = 'WIRE_ERROR';
    case EXCEPTION = 'EXCEPTION';
    case FATAL = 'FATAL';
    case ERROR = 'ERROR';
    case HISTORY = 'HISTORY';
    case HISTORY_ERROR = 'HISTORY_ERROR';
    case META = 'META';
    case TRUNCATED = 'TRUNCATED';
}
