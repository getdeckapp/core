<?php

use Deck\Core\Data\JobExecutionRecord;
use Deck\Core\Enums\JobExecutionStatus;
use Deck\Core\Events\JobExecutionRecorded;
use Deck\Core\Tests\Fixtures\SuccessfulTestJob;
use Illuminate\Support\Facades\Event;

/**
 * The kernel's job is to fire JobExecutionRecorded — with no database and no
 * sink of its own. This is the cross-package contract deck/deck and deck/cloud
 * subscribe to, so we assert the event, not any persistence.
 */
it('fires JobExecutionRecorded when a job is processed, without any sink', function () {
    Event::fake([JobExecutionRecorded::class]);

    SuccessfulTestJob::dispatch();

    Event::assertDispatched(JobExecutionRecorded::class, function (JobExecutionRecorded $event) {
        return $event->record instanceof JobExecutionRecord
            && $event->record->metadata->jobClass === SuccessfulTestJob::class
            && $event->record->status === JobExecutionStatus::Completed;
    });
});
