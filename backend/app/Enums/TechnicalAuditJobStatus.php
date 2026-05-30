<?php

namespace App\Enums;

enum TechnicalAuditJobStatus: string
{
    case Queued = 'queued';
    case Launching = 'launching';
    case Running = 'running';
    case Processing = 'processing';
    case Done = 'done';
    case Failed = 'failed';
}
