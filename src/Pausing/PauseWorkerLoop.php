<?php

namespace Deck\Core\Pausing;

use Illuminate\Queue\Events\Looping;

/**
 * Holds a queue worker idle while any queue it serves is paused.
 *
 * Laravel's worker asks the `Looping` event whether it may pop the next job;
 * a listener returning false makes it sleep for its configured `--sleep`
 * interval and ask again. That is exactly a pause: no job is popped, released,
 * or retried, and the worker resumes on its own once the flag is cleared.
 * Horizon worker processes use the same loop, so a pause covers them too.
 */
class PauseWorkerLoop
{
    public function handle(Looping $event): ?bool
    {
        if (QueuePause::shouldPauseWorker($event->connectionName, $event->queue)) {
            return false;
        }

        return null;
    }
}
