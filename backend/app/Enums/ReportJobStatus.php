<?php

namespace App\Enums;

enum ReportJobStatus: string
{
    case Queued = 'queued';
    case Fetching = 'fetching';
    case Rendering = 'rendering';
    case Done = 'done';
    case Failed = 'failed';
}
