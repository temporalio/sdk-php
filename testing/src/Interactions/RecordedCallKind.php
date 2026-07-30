<?php

declare(strict_types=1);

namespace Temporal\Testing\Interactions;

enum RecordedCallKind
{
    case Activity;
    case LocalActivity;
    case ChildWorkflow;
    case Timer;
    case Signal;
}
