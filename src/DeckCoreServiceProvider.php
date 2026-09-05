<?php

namespace Deck\Core;

use Deck\Core\Bus\DeckDispatcher;
use Deck\Core\Contracts\JobExecutionRecorder;
use Deck\Core\Dispatch\DeckObservability;
use Deck\Core\Events\JobExecutionRecorded;
use Deck\Core\Horizon\HorizonSnapshot;
use Deck\Core\Http\Middleware\AssignDispatchGroup;
use Deck\Core\Listeners\RecordJobExecution;
use Deck\Core\Pausing\PauseWorkerLoop;
use Deck\Core\Queue\DeckCallQueuedHandler;
use Deck\Core\Recorders\DispatchingJobExecutionRecorder;
use Illuminate\Bus\Dispatcher as BusDispatcher;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\Factory as QueueFactoryContract;
use Illuminate\Queue\CallQueuedHandler;
use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

/**
 * The Deck kernel: hooks the queue, records job lifecycle transitions, and
 * fires {@see JobExecutionRecorded}. It persists nothing and
 * knows nothing about a dashboard or Deck Cloud — sinks subscribe to the event
 * from their own packages (deck/deck for the database, deck/cloud for HTTP).
 */
class DeckCoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/deck.php', 'deck');

        $this->app->singleton(DispatchingJobExecutionRecorder::class);
        $this->app->singleton(
            JobExecutionRecorder::class,
            fn ($app): JobExecutionRecorder => $app->make(DispatchingJobExecutionRecorder::class),
        );
        $this->app->singleton(HorizonSnapshot::class, fn (): HorizonSnapshot => HorizonSnapshot::make());

        $this->registerDeckCallQueuedHandler();
    }

    public function boot(): void
    {
        $this->registerDeckDispatcher();
        $this->registerQueuePayloadStamping();
        $this->registerDispatchGroupMiddleware();
        $this->registerQueueListeners();
    }

    private function registerQueuePayloadStamping(): void
    {
        if (! DeckObservability::enabled()) {
            return;
        }

        Queue::createPayloadUsing(function (string $connection, ?string $queue, array $payload): array {
            return DeckObservability::stampQueuePayload($payload);
        });
    }

    private function registerDispatchGroupMiddleware(): void
    {
        if (! (bool) config('deck.dispatch_groups.enabled', true)) {
            return;
        }

        if (! (bool) config('deck.dispatch_groups.request_middleware', true)) {
            return;
        }

        $this->app->booted(function (): void {
            if ($this->app->runningInConsole()) {
                return;
            }

            $router = $this->app['router'];
            $router->pushMiddlewareToGroup('web', AssignDispatchGroup::class);
        });
    }

    private function registerDeckDispatcher(): void
    {
        $this->app->extend(BusDispatcher::class, function ($dispatcher, $app): DeckDispatcher {
            if ($dispatcher instanceof DeckDispatcher) {
                return $dispatcher;
            }

            return new DeckDispatcher($app, function (?string $connection = null) use ($app) {
                return $app->make(QueueFactoryContract::class)->connection($connection);
            });
        });
    }

    private function registerDeckCallQueuedHandler(): void
    {
        $factory = function ($app): DeckCallQueuedHandler {
            return new DeckCallQueuedHandler(
                $app->make(Dispatcher::class),
                $app,
            );
        };

        $this->app->singleton(DeckCallQueuedHandler::class, $factory);
        $this->app->singleton(CallQueuedHandler::class, fn ($app) => $app->make(DeckCallQueuedHandler::class));
    }

    private function registerQueueListeners(): void
    {
        $recorder = RecordJobExecution::class;

        Queue::before([$recorder, 'handleJobProcessing']);
        Queue::after([$recorder, 'handleProcessed']);
        Queue::failing([$recorder, 'handleFailed']);
        Queue::looping([PauseWorkerLoop::class, 'handle']);

        Event::listen(JobAttempted::class, [$recorder, 'handleJobAttempted']);
    }
}
