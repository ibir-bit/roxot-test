# Solution

## What Was Broken

* The daily stats API filter used different query parameter names: the dashboard sent `placement_id`, but the repository checked `placement`.
* Daily aggregation was not idempotent. Re-running `aggregate:daily` inserted duplicate `daily_stats` rows for the same placement and date.
* The aggregation date range was calculated in UTC instead of the business timezone from `APP_TIMEZONE`.
* The Go `/stat` endpoint accepted invalid events: unknown placements, unknown action types, and prices stored using the wrong representation instead of integer cents.

## How It Was Fixed

* `StatsRepository::findDailyStats()` now filters by the `placement_id` query parameter used by the dashboard.
* `daily_stats` has a unique constraint on `(placement_id, stat_date)`, and the aggregator writes rows using `INSERT ... ON CONFLICT DO UPDATE`.
* `DailyStatsAggregator` builds the daily range using `APP_TIMEZONE`, falling back to `UTC` if the variable is not set.
* The Go service validates `/stat` input before inserting into `raw_events`:

  * empty or unknown `placement` returns `400`;
  * unknown `actionType` returns `400`;
  * unsupported methods return `405`;
  * empty, invalid, or negative `price` returns `400`;
  * `price` is converted to cents, so `12.34` is stored as `1234`.

## How To Check

Run the integration check:

```bash
./scripts/check.sh
```

The script builds and starts the Docker services, seeds demo events, verifies invalid events are rejected, checks that `price=12.34` is stored as `1234` cents, runs aggregation twice to verify idempotency, and checks the `placement_id` API filter.

Expected result:

```text
All checks passed.
```

## Data Flow

Demo data starts with `seed:demo-events`. The command sends each demo event to the Go service:

```text
seed:demo-events
       ↓
Go /stat
       ↓
validation
       ↓
raw_events
       ↓
aggregate:daily
       ↓
daily_stats
       ↓
PHP /api/daily-stats
       ↓
dashboard
```

The Go service validates each event and stores only valid raw events in PostgreSQL.

Daily aggregation is then run by:

```bash
docker compose exec php php bin/console aggregate:daily 2026-08-07
```

The command reads `raw_events`, groups events by `placement_id` for the business day, and writes the aggregated totals to `daily_stats`:

```text
raw_events -> aggregate:daily -> daily_stats
```

The dashboard and Refresh button do not write statistics. They read the aggregated data through the PHP API:

```text
daily_stats -> /api/daily-stats -> dashboard
```

