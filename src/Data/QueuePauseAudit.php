<?php

namespace Deck\Core\Data;

use Illuminate\Support\Carbon;

readonly class QueuePauseAudit
{
    public function __construct(
        public ?string $reason,
        public Carbon $pausedAt,
        public ?string $pausedBy,
    ) {}

    /**
     * @param  array{reason?: string|null, paused_at?: string, paused_by?: string|null}|null  $payload
     */
    public static function fromCache(?array $payload): ?self
    {
        if ($payload === null || ! isset($payload['paused_at'])) {
            return null;
        }

        return new self(
            reason: isset($payload['reason']) && $payload['reason'] !== ''
                ? (string) $payload['reason']
                : null,
            pausedAt: Carbon::parse($payload['paused_at']),
            pausedBy: isset($payload['paused_by']) && $payload['paused_by'] !== ''
                ? (string) $payload['paused_by']
                : null,
        );
    }
}
