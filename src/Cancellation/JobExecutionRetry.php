<?php

namespace Deck\Core\Cancellation;

use Deck\Core\Data\JobExecutionRetryContext;
use Deck\Core\Data\RetryExecutionResult;
use Deck\Core\Enums\JobExecutionStatus;
use Deck\Core\Horizon\DeckHorizon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Artisan;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Jobs\RetryFailedJob;
use ReflectionClass;

class JobExecutionRetry
{
    public function retry(JobExecutionRetryContext $context): RetryExecutionResult
    {
        if ($context->status !== JobExecutionStatus::Failed) {
            return new RetryExecutionResult(
                success: false,
                message: 'Only failed executions can be retried.',
            );
        }

        if ($result = $this->retryViaHorizon($context)) {
            return $result;
        }

        if ($result = $this->retryViaQueueFailer($context)) {
            return $result;
        }

        return $this->retryByRedispatch($context);
    }

    protected function retryViaHorizon(JobExecutionRetryContext $context): ?RetryExecutionResult
    {
        if (! DeckHorizon::isInstalled()) {
            return null;
        }

        $failed = app(JobRepository::class)->findFailed($context->uuid);

        if ($failed === null) {
            return null;
        }

        dispatch(new RetryFailedJob($context->uuid));

        return new RetryExecutionResult(
            success: true,
            message: 'Job has been queued for retry using the original Horizon payload.',
        );
    }

    protected function retryViaQueueFailer(JobExecutionRetryContext $context): ?RetryExecutionResult
    {
        if (! app()->bound('queue.failer')) {
            return null;
        }

        $failer = app('queue.failer');

        try {
            $failed = $failer->find($context->uuid);
        } catch (\Throwable) {
            return null;
        }

        if ($failed === null) {
            return null;
        }

        Artisan::call('queue:retry', ['id' => [$context->uuid]]);

        return new RetryExecutionResult(
            success: true,
            message: 'Job has been queued for retry using the stored failed-job payload.',
        );
    }

    protected function retryByRedispatch(JobExecutionRetryContext $context): RetryExecutionResult
    {
        $jobClass = $context->jobClass;

        if (! class_exists($jobClass)) {
            return new RetryExecutionResult(
                success: false,
                message: "Job class [{$jobClass}] does not exist.",
            );
        }

        $reflection = new ReflectionClass($jobClass);

        if (! $reflection->implementsInterface(ShouldQueue::class)) {
            return new RetryExecutionResult(
                success: false,
                message: 'Job class does not implement ShouldQueue.',
            );
        }

        if (! $reflection->isInstantiable()) {
            return new RetryExecutionResult(
                success: false,
                message: 'Job class cannot be instantiated without the original failed-job payload.',
            );
        }

        $constructor = $reflection->getConstructor();

        if ($constructor !== null && $constructor->getNumberOfRequiredParameters() > 0) {
            return new RetryExecutionResult(
                success: false,
                message: 'Job requires constructor arguments. Use Horizon or the database failed_jobs driver so the original payload can be retried.',
            );
        }

        /** @var ShouldQueue $job */
        $job = $reflection->newInstance();

        $pending = dispatch($job);

        if ($context->connection !== '') {
            $pending->onConnection($context->connection);
        }

        if ($context->queue !== '') {
            $pending->onQueue($context->queue);
        }

        return new RetryExecutionResult(
            success: true,
            message: 'A new job instance was dispatched without the original payload. Verify constructor dependencies if the job needs them.',
        );
    }
}
