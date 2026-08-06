<?php

namespace Deck\Core\Listeners;

use Deck\Core\Blocking\InterceptBlockedQueueJob;
use Deck\Core\Blocking\JobClassBlock;
use Deck\Core\Blocking\JobClassIdentifierRegistry;
use Deck\Core\Cancellation\JobCancellation;
use Deck\Core\Contracts\JobExecutionRecorder;
use Deck\Core\Data\JobExecutionRecord;
use Deck\Core\Enums\JobExecutionStatus;
use Deck\Core\Exceptions\JobCancelledException;
use Deck\Core\Recording\JobExecutionTiming;
use Deck\Core\Recording\JobProgress;
use Deck\Core\Recording\QueuedJobMetadata;
use Deck\Core\Support\DeckInstallation;
use Deck\Core\Support\DeckResilience;
use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Carbon;

class RecordJobExecution
{
    public function __construct(
        private JobExecutionRecorder $recorder,
    ) {}

    public function handleJobProcessing(JobProcessing $event): void
    {
        if (InterceptBlockedQueueJob::intercept($event->job)) {
            return;
        }

        $this->handleProcessing($event);
    }

    public function handleProcessing(JobProcessing $event): void
    {
        DeckResilience::runSilentlyVoid(function () use ($event): void {
            $metadata = QueuedJobMetadata::fromQueueJob($event->job);

            if (JobClassBlock::isBlockedForJob($event->job)) {
                return;
            }

            JobClassIdentifierRegistry::rememberFromQueueJob($event->job);

            $startedAt = Carbon::now();
            $waitMs = $this->resolveWaitMs($metadata, $startedAt);
            JobExecutionTiming::remember($metadata->uuid, $metadata->attempt, $startedAt, $waitMs);

            $this->recorder->record(new JobExecutionRecord(
                metadata: $metadata,
                project: DeckInstallation::project(),
                environment: DeckInstallation::environment(),
                status: JobExecutionStatus::Running,
                startedAt: $startedAt,
                waitMs: $waitMs,
                tags: $metadata->tags,
            ));
        });
    }

    public function handleProcessed(JobProcessed $event): void
    {
        $this->recordProcessed($event);
    }

    public function handleFailed(JobFailed $event): void
    {
        $this->recordFailed($event);
    }

    /**
     * Ensures terminal status is persisted when earlier listeners did not run
     * (defer bugs, swallowed errors, or jobs that skipped the running row).
     */
    public function handleJobAttempted(JobAttempted $event): void
    {
        if (in_array($event->connectionName, ['sync', 'deferred'], true)) {
            return;
        }

        DeckResilience::runSilentlyVoid(function () use ($event): void {
            $metadata = QueuedJobMetadata::fromQueueJob($event->job);

            $state = JobExecutionTiming::peek($metadata->uuid, $metadata->attempt);

            // Already finalized (terminal) or intercepted (blocked): there is
            // nothing to backfill. Clean up the lingering marker and bail.
            if ($state !== null && ($state->isTerminal() || $state->isBlocked())) {
                JobExecutionTiming::forget($metadata->uuid, $metadata->attempt);

                return;
            }

            // Never observed starting (skipped the running row): synthesize it so
            // the terminal record has a start time to anchor to.
            if ($state === null) {
                $this->handleProcessing(new JobProcessing($event->connectionName, $event->job));
            }

            if ($event->successful()) {
                $this->recordProcessed(new JobProcessed($event->connectionName, $event->job));

                return;
            }

            if ($event->exception !== null) {
                $this->recordFailed(new JobFailed($event->connectionName, $event->job, $event->exception));
            }
        });
    }

    private function recordProcessed(JobProcessed $event): void
    {
        DeckResilience::runSilentlyVoid(function () use ($event): void {
            $metadata = QueuedJobMetadata::fromQueueJob($event->job);
            $finishedAt = Carbon::now();

            $state = JobExecutionTiming::peek($metadata->uuid, $metadata->attempt);

            if ($state?->isBlocked()) {
                JobExecutionTiming::forget($metadata->uuid, $metadata->attempt);

                return;
            }

            $startedAt = $state->startedAt ?? $finishedAt;

            $wasCancelled = JobCancellation::consumeIfCancelled($metadata->uuid);

            $this->recorder->record(new JobExecutionRecord(
                metadata: $metadata,
                project: DeckInstallation::project(),
                environment: DeckInstallation::environment(),
                status: $wasCancelled ? JobExecutionStatus::Cancelled : JobExecutionStatus::Completed,
                startedAt: $startedAt,
                finishedAt: $finishedAt,
                durationMs: (int) $startedAt->diffInMilliseconds($finishedAt),
                waitMs: $state?->waitMs,
                tags: $metadata->tags,
            ));

            JobExecutionTiming::markTerminal($metadata->uuid, $metadata->attempt);
            JobProgress::clear($metadata->uuid);
        });
    }

    private function recordFailed(JobFailed $event): void
    {
        DeckResilience::runSilentlyVoid(function () use ($event): void {
            $metadata = QueuedJobMetadata::fromQueueJob($event->job);
            $finishedAt = Carbon::now();
            $exception = $event->exception;

            $state = JobExecutionTiming::peek($metadata->uuid, $metadata->attempt);
            $startedAt = $state->startedAt ?? $finishedAt;

            $isCancelled = $exception instanceof JobCancelledException;

            if ($isCancelled) {
                JobCancellation::consumeIfCancelled($metadata->uuid);
            }

            $this->recorder->record(new JobExecutionRecord(
                metadata: $metadata,
                project: DeckInstallation::project(),
                environment: DeckInstallation::environment(),
                status: $isCancelled ? JobExecutionStatus::Cancelled : JobExecutionStatus::Failed,
                startedAt: $startedAt,
                finishedAt: $finishedAt,
                durationMs: (int) $startedAt->diffInMilliseconds($finishedAt),
                waitMs: $state?->waitMs,
                tags: $metadata->tags,
                exceptionClass: $isCancelled ? null : $exception::class,
                exceptionMessage: $isCancelled ? null : $this->truncateExceptionMessage($exception->getMessage()),
                exceptionTrace: $isCancelled ? null : $this->truncateExceptionTrace($exception),
            ));

            JobExecutionTiming::markTerminal($metadata->uuid, $metadata->attempt);
            JobProgress::clear($metadata->uuid);
        });
    }

    private function truncateExceptionMessage(string $message): string
    {
        return mb_substr($message, 0, 2000);
    }

    private function truncateExceptionTrace(\Throwable $exception): string
    {
        $limit = max(1_024, (int) config('deck.exception_trace_bytes', 65_536));

        return mb_substr($exception->getTraceAsString(), 0, $limit);
    }

    private function resolveWaitMs(QueuedJobMetadata $metadata, Carbon $startedAt): ?int
    {
        $dispatchedAt = $metadata->observability?->dispatchedAt;

        if ($dispatchedAt === null) {
            return null;
        }

        return max(0, (int) $dispatchedAt->diffInMilliseconds($startedAt));
    }
}
