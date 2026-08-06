<?php

namespace Deck\Core\Contracts;

use Deck\Core\Data\JobExecutionRecord;
use Deck\Core\Recorders\DispatchingJobExecutionRecorder;

/**
 * Records job execution lifecycle transitions.
 *
 * The bound implementation dispatches a JobExecutionRecorded event; the
 * database and Deck Cloud sinks subscribe to it.
 *
 * @see DispatchingJobExecutionRecorder Default producer-facing implementation
 */
interface JobExecutionRecorder
{
    public function record(JobExecutionRecord $record): void;
}
