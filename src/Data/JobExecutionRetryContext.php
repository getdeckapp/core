<?php

namespace Deck\Core\Data;

use Deck\Core\Cancellation\JobExecutionRetry;
use Deck\Core\Enums\JobExecutionStatus;

/**
 * The minimal, storage-free context needed to retry a job execution.
 *
 * The full app (deck/deck) builds this from its local Eloquent record; the
 * cloud agent (deck/cloud) builds it from the remote command payload. Either
 * way {@see JobExecutionRetry} works off this DTO and
 * never touches a database.
 */
readonly class JobExecutionRetryContext
{
    public function __construct(
        public string $uuid,
        public JobExecutionStatus $status,
        public string $jobClass,
        public string $connection = '',
        public string $queue = '',
    ) {}
}
