<?php

namespace Deck\Core\Blocking;

use Deck\Core\Contracts\JobExecutionRecorder;
use Deck\Core\Data\JobExecutionRecord;
use Deck\Core\Enums\JobExecutionStatus;
use Deck\Core\Recording\JobExecutionTiming;
use Deck\Core\Recording\QueuedJobMetadata;
use Deck\Core\Support\Concerns\RunsSilently;
use Deck\Core\Support\DeckInstallation;
use Deck\Core\Support\DeferDeckSideEffects;

class BlockedJobExecutionRecorder
{
    use RunsSilently;

    public static function record(QueuedJobMetadata $metadata): void
    {
        DeferDeckSideEffects::run(fn () => static::recordNow($metadata));
    }

    public static function recordNow(QueuedJobMetadata $metadata): void
    {
        static::runSilentlyVoid(function () use ($metadata): void {
            static::recordNowUnchecked($metadata);
        });
    }

    private static function recordNowUnchecked(QueuedJobMetadata $metadata): void
    {
        if (JobExecutionTiming::peek($metadata->uuid, $metadata->attempt)?->isBlocked()) {
            return;
        }

        $now = now();

        app(JobExecutionRecorder::class)->record(new JobExecutionRecord(
            metadata: $metadata,
            project: DeckInstallation::project(),
            environment: DeckInstallation::environment(),
            status: JobExecutionStatus::Blocked,
            startedAt: $now,
            finishedAt: $now,
            durationMs: 0,
            waitMs: 0,
            tags: $metadata->tags,
        ));

        JobExecutionTiming::markBlocked($metadata->uuid, $metadata->attempt);
    }
}
