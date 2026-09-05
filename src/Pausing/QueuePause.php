<?php

namespace Deck\Core\Pausing;

use Deck\Core\Data\QueuePauseAudit;
use Deck\Core\Support\Concerns\RunsSilently;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Cache-backed pause flag for a connection:queue pair.
 *
 * A paused queue keeps accepting dispatches; workers that serve it simply stop
 * popping jobs until the flag is cleared (see {@see PauseWorkerLoop}). Nothing
 * is deleted, released, or retried, so pausing is side-effect free and safe to
 * hold for as long as an incident needs.
 */
class QueuePause
{
    use RunsSilently;

    private const string Marker = 'paused';

    public static function cacheKey(string $connection, string $queue): string
    {
        return 'deck:pause:'.hash('sha256', static::identity($connection, $queue));
    }

    public static function auditCacheKey(string $connection, string $queue): string
    {
        return 'deck:pause:audit:'.hash('sha256', static::identity($connection, $queue));
    }

    public static function pause(string $connection, string $queue, ?string $reason = null): void
    {
        static::runSilentlyVoid(function () use ($connection, $queue, $reason): void {
            $expiresAt = now()->addSeconds(static::ttlSeconds());

            static::cache()->put(static::cacheKey($connection, $queue), self::Marker, $expiresAt);
            static::cache()->put(
                static::auditCacheKey($connection, $queue),
                [
                    'reason' => static::normalizeReason($reason),
                    'paused_at' => now()->toIso8601String(),
                    'paused_by' => static::resolvePausedBy(),
                ],
                $expiresAt,
            );
        });
    }

    public static function resume(string $connection, string $queue): void
    {
        static::runSilentlyVoid(function () use ($connection, $queue): void {
            static::cache()->forget(static::cacheKey($connection, $queue));
            static::cache()->forget(static::auditCacheKey($connection, $queue));
        });
    }

    public static function isPaused(string $connection, string $queue): bool
    {
        return static::isAnyPaused($connection, [$queue]);
    }

    /**
     * Whether any of the given queues on the connection is paused. One cache
     * round-trip regardless of how many queues a worker serves.
     *
     * @param  list<string>  $queues
     */
    public static function isAnyPaused(string $connection, array $queues): bool
    {
        $queues = array_values(array_filter(array_map('trim', $queues), fn (string $queue): bool => $queue !== ''));

        if ($queues === []) {
            return false;
        }

        return static::runSilently(function () use ($connection, $queues): bool {
            $keys = array_map(fn (string $queue): string => static::cacheKey($connection, $queue), $queues);
            $values = static::cache()->many($keys);

            foreach ($values as $value) {
                if ($value === self::Marker) {
                    return true;
                }
            }

            return false;
        }, false);
    }

    /**
     * Whether a worker started for the given connection and queue list (the
     * comma-separated `--queue` option, or null for the connection default)
     * should sit idle.
     */
    public static function shouldPauseWorker(string $connection, ?string $queue): bool
    {
        return static::isAnyPaused($connection, static::queuesForWorker($connection, $queue));
    }

    public static function audit(string $connection, string $queue): ?QueuePauseAudit
    {
        if (! static::isPaused($connection, $queue)) {
            return null;
        }

        $payload = static::runSilently(fn (): mixed => static::cache()->get(static::auditCacheKey($connection, $queue)));

        if (! is_array($payload)) {
            return null;
        }

        return QueuePauseAudit::fromCache($payload);
    }

    /**
     * Resolve the queue names a worker process serves. A null queue means the
     * connection's configured default.
     *
     * @return list<string>
     */
    public static function queuesForWorker(string $connection, ?string $queue): array
    {
        if ($queue === null || trim($queue) === '') {
            $default = config("queue.connections.{$connection}.queue", 'default');

            if (is_array($default)) {
                $default = $default[0] ?? 'default';
            }

            $default = (string) $default;

            return [$default !== '' ? $default : 'default'];
        }

        return array_values(array_filter(array_map('trim', explode(',', $queue)), fn (string $name): bool => $name !== ''));
    }

    public static function cacheRepository(): CacheRepository
    {
        $store = config('deck.pause_cache_store')
            ?? config('deck.block_cache_store')
            ?? config('deck.cancel_cache_store');

        if ($store === null && config('queue.default') === 'redis') {
            $store = 'redis';
        }

        return app('cache')->store($store ?? config('cache.default'));
    }

    private static function identity(string $connection, string $queue): string
    {
        return trim($connection).':'.trim($queue);
    }

    private static function ttlSeconds(): int
    {
        return max(60, (int) config('deck.pause_ttl_seconds', 31_536_000));
    }

    private static function normalizeReason(?string $reason): ?string
    {
        if ($reason === null) {
            return null;
        }

        $trimmed = trim($reason);

        if ($trimmed === '') {
            return null;
        }

        $maxLength = max(1, (int) config('deck.block_reason_max_length', 500));

        return mb_substr($trimmed, 0, $maxLength);
    }

    private static function resolvePausedBy(): ?string
    {
        $user = auth()->user();

        if ($user === null) {
            return null;
        }

        $email = $user->getAttribute('email');

        if (is_string($email) && $email !== '') {
            return $email;
        }

        $name = $user->getAttribute('name');

        if (is_string($name) && $name !== '') {
            return $name;
        }

        return null;
    }

    private static function cache(): CacheRepository
    {
        return static::cacheRepository();
    }
}
