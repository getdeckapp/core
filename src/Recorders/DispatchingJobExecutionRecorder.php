<?php

namespace Deck\Core\Recorders;

use Deck\Core\Contracts\JobExecutionRecorder;
use Deck\Core\Data\JobExecutionRecord;
use Deck\Core\Events\JobExecutionRecorded;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * The producer-facing recorder. Translates a record() call into a
 * JobExecutionRecorded event, which the database and cloud sinks consume.
 */
class DispatchingJobExecutionRecorder implements JobExecutionRecorder
{
    public function __construct(
        private readonly Dispatcher $events,
    ) {}

    public function record(JobExecutionRecord $record): void
    {
        $this->events->dispatch(new JobExecutionRecorded($record));
    }
}
