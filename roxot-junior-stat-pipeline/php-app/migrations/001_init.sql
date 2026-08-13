CREATE TABLE publishers (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL
);

CREATE TABLE sites (
    id TEXT PRIMARY KEY,
    publisher_id TEXT NOT NULL REFERENCES publishers(id),
    domain TEXT NOT NULL
);

CREATE TABLE placements (
    id TEXT PRIMARY KEY,
    site_id TEXT NOT NULL REFERENCES sites(id),
    code TEXT NOT NULL,
    format TEXT NOT NULL
);

CREATE TABLE raw_events (
    id BIGSERIAL PRIMARY KEY,
    placement_id TEXT NOT NULL,
    action_type TEXT NOT NULL,
    price_cents INTEGER NOT NULL DEFAULT 0,
    occurred_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    request_id TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX raw_events_occurred_at_idx ON raw_events (occurred_at);
CREATE INDEX raw_events_placement_id_idx ON raw_events (placement_id);

CREATE TABLE daily_stats (
    id BIGSERIAL PRIMARY KEY,
    stat_date DATE NOT NULL,
    placement_id TEXT NOT NULL,
    impressions INTEGER NOT NULL DEFAULT 0,
    clicks INTEGER NOT NULL DEFAULT 0,
    revenue_cents INTEGER NOT NULL DEFAULT 0,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

INSERT INTO publishers (id, name) VALUES
    ('publisher-demo', 'Demo Publisher');

INSERT INTO sites (id, publisher_id, domain) VALUES
    ('site-news', 'publisher-demo', 'news.example.test'),
    ('site-sport', 'publisher-demo', 'sport.example.test');

INSERT INTO placements (id, site_id, code, format) VALUES
    ('placement-video-main', 'site-news', 'video-main', 'video'),
    ('placement-banner-sidebar', 'site-news', 'banner-sidebar', 'banner'),
    ('placement-sport-top', 'site-sport', 'sport-top', 'banner');

