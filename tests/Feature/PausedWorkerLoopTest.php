<?php

use Deck\Core\Pausing\QueuePause;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Event;

/*
 * Laravel's worker asks the Looping event whether it may pop a job before every
 * iteration; the first non-null answer wins and `false` makes it sleep instead.
 * Deck answers false while any queue the worker serves is paused.
 */

it('lets workers loop normally when nothing is paused', function () {
    expect(Event::until(new Looping('redis', 'default')))->toBeNull();
});

it('holds a worker idle while its queue is paused', function () {
    QueuePause::pause('redis', 'default');

    expect(Event::until(new Looping('redis', 'default')))->toBeFalse()
        ->and(Event::until(new Looping('redis', 'emails,default')))->toBeFalse()
        ->and(Event::until(new Looping('redis', 'emails')))->toBeNull()
        ->and(Event::until(new Looping('database', 'default')))->toBeNull();
});

it('releases the worker as soon as the queue is resumed', function () {
    QueuePause::pause('redis', 'default');
    QueuePause::resume('redis', 'default');

    expect(Event::until(new Looping('redis', 'default')))->toBeNull();
});

it('treats a null queue option as the connection default queue', function () {
    config()->set('queue.connections.redis.queue', 'primary');

    QueuePause::pause('redis', 'primary');

    expect(Event::until(new Looping('redis', null)))->toBeFalse();
});
