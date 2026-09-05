<?php

use Deck\Core\Pausing\QueuePause;

it('pauses and resumes a connection queue', function () {
    QueuePause::pause('redis', 'default');

    expect(QueuePause::isPaused('redis', 'default'))->toBeTrue()
        ->and(QueuePause::isPaused('redis', 'emails'))->toBeFalse()
        ->and(QueuePause::isPaused('database', 'default'))->toBeFalse();

    QueuePause::resume('redis', 'default');

    expect(QueuePause::isPaused('redis', 'default'))->toBeFalse();
});

it('reports paused when any queue in a worker list is paused', function () {
    QueuePause::pause('redis', 'emails');

    expect(QueuePause::isAnyPaused('redis', ['default', 'emails']))->toBeTrue()
        ->and(QueuePause::isAnyPaused('redis', ['default', 'reports']))->toBeFalse()
        ->and(QueuePause::isAnyPaused('redis', []))->toBeFalse();
});

it('resolves the worker queue list from the option or the connection default', function () {
    config()->set('queue.connections.redis.queue', 'primary');

    expect(QueuePause::queuesForWorker('redis', null))->toBe(['primary'])
        ->and(QueuePause::queuesForWorker('redis', 'default, emails ,reports'))->toBe(['default', 'emails', 'reports'])
        ->and(QueuePause::queuesForWorker('sqs', null))->toBe(['default']);
});

it('decides whether a worker should pause from its connection and queue option', function () {
    config()->set('queue.connections.redis.queue', 'default');

    QueuePause::pause('redis', 'default');

    expect(QueuePause::shouldPauseWorker('redis', null))->toBeTrue()
        ->and(QueuePause::shouldPauseWorker('redis', 'emails,default'))->toBeTrue()
        ->and(QueuePause::shouldPauseWorker('redis', 'emails'))->toBeFalse()
        ->and(QueuePause::shouldPauseWorker('database', 'default'))->toBeFalse();
});

it('stores an optional reason for auditing and clears it on resume', function () {
    QueuePause::pause('redis', 'default', 'Draining before the schema migration');

    $audit = QueuePause::audit('redis', 'default');

    expect($audit)->not->toBeNull()
        ->and($audit->reason)->toBe('Draining before the schema migration')
        ->and($audit->pausedAt)->not->toBeNull();

    QueuePause::resume('redis', 'default');

    expect(QueuePause::audit('redis', 'default'))->toBeNull();
});

it('truncates overly long pause reasons', function () {
    config()->set('deck.block_reason_max_length', 12);

    QueuePause::pause('redis', 'default', str_repeat('x', 40));

    expect(QueuePause::audit('redis', 'default')?->reason)->toHaveLength(12);
});

it('expires a pause after the configured ttl', function () {
    config()->set('deck.pause_ttl_seconds', 60);

    QueuePause::pause('redis', 'default');

    $this->travel(61)->seconds();

    expect(QueuePause::isPaused('redis', 'default'))->toBeFalse();
});
