<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class StatsRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findDailyStats(array $query): array
    {
        $date = $query['date'] ?? date('Y-m-d');
        $where = ['ds.stat_date = :date'];
        $params = ['date' => $date];

        if (!empty($query['placement_id'])) {
            $where[] = 'ds.placement_id = :placement_id';
            $params['placement_id'] = $query['placement_id'];
        }

        $sql = sprintf(
            'SELECT
                ds.stat_date,
                ds.placement_id,
                p.code AS placement_code,
                p.format,
                s.domain,
                ds.impressions,
                ds.clicks,
                ds.revenue_cents,
                ROUND(ds.revenue_cents / 100.0, 2) AS revenue
             FROM daily_stats ds
             LEFT JOIN placements p ON p.id = ds.placement_id
             LEFT JOIN sites s ON s.id = p.site_id
             WHERE %s
             ORDER BY ds.placement_id',
            implode(' AND ', $where)
        );

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }
}

