<?php

declare(strict_types=1);

namespace App\Aggregation;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class DailyStatsAggregator
{
    public function __construct(private PDO $pdo)
    {
    }

    public function aggregate(string $date): int
    {
        $start = new DateTimeImmutable($date . ' 00:00:00', new DateTimeZone('UTC'));
        $end = $start->add(new DateInterval('P1D'));

        $select = $this->pdo->prepare(
            "SELECT
                placement_id,
                SUM(CASE WHEN action_type = 'impression' THEN 1 ELSE 0 END) AS impressions,
                SUM(CASE WHEN action_type = 'click' THEN 1 ELSE 0 END) AS clicks,
                SUM(price_cents) AS revenue_cents
             FROM raw_events
             WHERE occurred_at >= :start_at AND occurred_at < :end_at
             GROUP BY placement_id
             ORDER BY placement_id"
        );

        $select->execute([
            'start_at' => $start->format(DATE_ATOM),
            'end_at' => $end->format(DATE_ATOM),
        ]);

        $rows = $select->fetchAll();
        $insert = $this->pdo->prepare(
            'INSERT INTO daily_stats (stat_date, placement_id, impressions, clicks, revenue_cents, updated_at)
             VALUES (:stat_date, :placement_id, :impressions, :clicks, :revenue_cents, now())'
        );

        foreach ($rows as $row) {
            $insert->execute([
                'stat_date' => $date,
                'placement_id' => $row['placement_id'],
                'impressions' => (int) $row['impressions'],
                'clicks' => (int) $row['clicks'],
                'revenue_cents' => (int) $row['revenue_cents'],
            ]);
        }

        return count($rows);
    }
}

