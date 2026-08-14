#!/usr/bin/env bash
set -euo pipefail

PHP_API_URL="${PHP_API_URL:-http://127.0.0.1:18080}"
GO_STAT_URL="${GO_STAT_URL:-http://127.0.0.1:17011}"

log() {
  printf '\n==> %s\n' "$1"
}

fail() {
  printf 'FAIL: %s\n' "$1" >&2
  exit 1
}

assert_eq() {
  local actual="$1"
  local expected="$2"
  local message="$3"

  if [ "$actual" != "$expected" ]; then
    fail "$message: expected '$expected', got '$actual'"
  fi
}

psql_value() {
  docker compose exec -T postgres psql -U app -d stat_pipeline -Atc "$1"
}

log "Build and start services"
docker compose up -d --build

log "Seed demo events"
seed_output="$(docker compose exec -T php php bin/console seed:demo-events)"
printf '%s\n' "$seed_output"

case "$seed_output" in
  *"Sent 6 event(s)"*"4 accepted, 2 rejected"*) ;;
  *) fail "seed:demo-events should accept 4 valid events and reject 2 invalid events" ;;
esac

raw_bad_count="$(psql_value "SELECT COUNT(*) FROM raw_events WHERE placement_id = 'missing-placement' OR action_type = 'unknown_action';")"
assert_eq "$raw_bad_count" "0" "bad demo events must not be stored in raw_events"

log "Validate bad /stat requests"
empty_placement_status="$(curl -s -o /dev/null -w "%{http_code}" "${GO_STAT_URL}/stat?actionType=impression&price=1.00&requestId=check-empty-placement")"
unknown_placement_status="$(curl -s -o /dev/null -w "%{http_code}" "${GO_STAT_URL}/stat?actionType=impression&placement=missing-placement&price=1.00&requestId=check-missing-placement")"
unknown_action_status="$(curl -s -o /dev/null -w "%{http_code}" "${GO_STAT_URL}/stat?actionType=unknown_action&placement=placement-video-main&price=1.00&requestId=check-bad-action")"
empty_price_status="$(curl -s -o /dev/null -w "%{http_code}" "${GO_STAT_URL}/stat?actionType=impression&placement=placement-video-main&requestId=check-empty-price")"
invalid_price_status="$(curl -s -o /dev/null -w "%{http_code}" "${GO_STAT_URL}/stat?actionType=impression&placement=placement-video-main&price=abc&requestId=check-invalid-price")"
negative_price_status="$(curl -s -o /dev/null -w "%{http_code}" "${GO_STAT_URL}/stat?actionType=impression&placement=placement-video-main&price=-1.00&requestId=check-negative-price")"
method_status="$(curl -s -o /dev/null -w "%{http_code}" -X POST "${GO_STAT_URL}/stat")"

assert_eq "$empty_placement_status" "400" "empty placement should return HTTP 400"
assert_eq "$unknown_placement_status" "400" "unknown placement should return HTTP 400"
assert_eq "$unknown_action_status" "400" "unknown actionType should return HTTP 400"
assert_eq "$empty_price_status" "400" "empty price should return HTTP 400"
assert_eq "$invalid_price_status" "400" "invalid price should return HTTP 400"
assert_eq "$negative_price_status" "400" "negative price should return HTTP 400"
assert_eq "$method_status" "405" "unsupported method should return HTTP 405"

log "Validate price cents conversion"
curl -fsS "${GO_STAT_URL}/stat?actionType=impression&placement=placement-video-main&price=12.34&requestId=price-check" >/dev/null
price_cents="$(psql_value "SELECT price_cents FROM raw_events WHERE request_id = 'price-check';")"
assert_eq "$price_cents" "1234" "price=12.34 should be stored as 1234 cents"

log "Run daily aggregation twice"
docker compose exec -T php php bin/console aggregate:daily 2026-08-07
docker compose exec -T php php bin/console aggregate:daily 2026-08-07

duplicate_count="$(psql_value "SELECT COUNT(*) FROM (SELECT placement_id, stat_date FROM daily_stats GROUP BY placement_id, stat_date HAVING COUNT(*) > 1) duplicates;")"
assert_eq "$duplicate_count" "0" "daily_stats should not contain duplicate placement/date rows"

daily_rows="$(psql_value "SELECT COUNT(*) FROM daily_stats WHERE stat_date = '2026-08-07';")"
assert_eq "$daily_rows" "2" "aggregation should produce two placement rows for the seeded business day"

timezone_main_impressions="$(psql_value "SELECT impressions FROM daily_stats WHERE stat_date = '2026-08-07' AND placement_id = 'placement-video-main';")"
assert_eq "$timezone_main_impressions" "1" "Europe/Moscow business day should include only the 21:05 UTC main impression for 2026-08-07"

log "Validate daily-stats placement_id filter"
stats_json="$(curl -fsS "${PHP_API_URL}/api/daily-stats?date=2026-08-07&placement_id=placement-video-main")"
case "$stats_json" in
  *'"placement_id":"placement-video-main"'*) ;;
  *) fail "filtered API response should contain placement-video-main" ;;
esac

case "$stats_json" in
  *'"placement_id":"placement-banner-sidebar"'*|*'"placement_id":"placement-sport-top"'*)
    fail "filtered API response contains another placement"
    ;;
esac

printf '\nAll checks passed.\n'
