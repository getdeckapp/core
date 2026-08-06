# Deck Core

**The queue-instrumentation kernel behind [Deck](https://github.com/getdeckapp/deck).**

`deck/core` hooks Laravel's queue, records every job-execution lifecycle
transition into a `JobExecutionRecord`, and fires a single `JobExecutionRecorded`
event. It has **no database and no dashboard** — sinks subscribe to the event
from other packages:

- [`deck/deck`](https://github.com/getdeckapp/deck) persists executions to your
  database and renders the dashboard.
- [`deck/cloud`](https://github.com/getdeckapp/cloud) streams them to Deck Cloud.

You normally don't install this package directly — you install `deck/deck` (full
self-hosted app) or `deck/cloud` (slim agent), both of which require it.

## What's in here

- Queue payload stamping, the Deck bus dispatcher, and the queued-job handler.
- The recorder → `JobExecutionRecorded` event seam and the `JobExecutionRecorder`
  contract.
- Cache-based control primitives: cooperative cancellation, pending-job
  cancellation, and job-class blocking.
- The storage-free retry primitive (`JobExecutionRetry` + `JobExecutionRetryContext`).
- Shared data objects (`JobExecutionRecord`, `ObservabilitySnapshot`, …) and
  Horizon read helpers.

## The cross-package contract

`Deck\Core\Events\JobExecutionRecorded` carrying `Deck\Core\Data\JobExecutionRecord`
is the seam every sink builds on. Treat that DTO as a versioned public contract.

## Requirements

PHP 8.3+ · Laravel 11–13 · Redis (for cancel/block flags). [Horizon](https://laravel.com/docs/horizon) 5.x is optional and enables richer worker snapshots and failed-job retry.

## Installation

```bash
composer require deck/core
```

The service provider (`Deck\Core\DeckCoreServiceProvider`) is auto-discovered.

## Development

```bash
composer test
composer analyse
```

## License

MIT © [Tor Morten Jensen](https://github.com/tormjens). See [LICENSE.md](LICENSE.md).
