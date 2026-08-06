<?php

namespace Deck\Core\Enums;

enum JobExecutionStatus: string
{
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Blocked = 'blocked';
}
