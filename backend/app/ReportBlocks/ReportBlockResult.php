<?php

namespace App\ReportBlocks;

class ReportBlockResult
{
    public function __construct(
        public string $html,
        public ?string $title = null,
        public bool $success = true,
    ) {}
}
