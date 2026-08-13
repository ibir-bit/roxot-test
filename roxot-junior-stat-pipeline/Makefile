up:
	docker compose up -d --build

down:
	docker compose down

logs:
	docker compose logs -f php go-stat

seed:
	docker compose exec php php bin/console seed:demo-events

aggregate:
	docker compose exec php php bin/console aggregate:daily 2026-08-07

stats:
	curl "http://127.0.0.1:18080/api/daily-stats?date=2026-08-07&placement_id=placement-video-main"

