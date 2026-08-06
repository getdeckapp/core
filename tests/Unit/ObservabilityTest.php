<?php

use Deck\Core\Dispatch\DeckObservability;
use Deck\Core\Dispatch\DispatchGroup;
use Deck\Core\Enums\DispatchGroupSource;

it('stamps queue payloads with dispatch group metadata', function (): void {
    $payload = DeckObservability::stampQueuePayload([
        'uuid' => (string) str()->uuid(),
        'displayName' => 'App\\Jobs\\Example',
    ]);

    DispatchGroup::using('campaign-123', function () use (&$childPayload): void {
        $childPayload = DeckObservability::stampQueuePayload([
            'uuid' => (string) str()->uuid(),
            'displayName' => 'App\\Jobs\\ChildJob',
        ]);
    }, DispatchGroupSource::Manual);

    expect($payload['deck']['dispatched_at'] ?? null)->not->toBeNull()
        ->and($childPayload['deck']['dispatch_group']['id'] ?? null)->toBe('campaign-123')
        ->and($childPayload['deck']['dispatch_group']['source'] ?? null)->toBe('manual');
});

it('parses observability data from deck queue payloads', function (): void {
    $snapshot = DeckObservability::snapshotFromDeckPayload([
        'dispatched_at' => '2026-05-25T14:02:01+00:00',
        'dispatch_group' => [
            'id' => 'req-abc',
            'source' => 'request',
        ],
        'parent_job' => [
            'uuid' => '550e8400-e29b-41d4-a716-446655440000',
            'class' => 'App\\Jobs\\ParentJob',
        ],
    ], batchId: '6ba7b810-9dad-11d1-80b4-00c04fd430c8');

    expect($snapshot->dispatchedAt?->utc()->toIso8601String())->toBe('2026-05-25T14:02:01+00:00')
        ->and($snapshot->dispatchGroupId)->toBe('req-abc')
        ->and($snapshot->dispatchGroupSource)->toBe(DispatchGroupSource::Request)
        ->and($snapshot->batchId)->toBe('6ba7b810-9dad-11d1-80b4-00c04fd430c8')
        ->and($snapshot->parentJobUuid)->toBe('550e8400-e29b-41d4-a716-446655440000')
        ->and($snapshot->parentJobClass)->toBe('App\\Jobs\\ParentJob');
});
