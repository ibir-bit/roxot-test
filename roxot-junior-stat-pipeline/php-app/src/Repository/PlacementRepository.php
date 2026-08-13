<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class PlacementRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findAll(): array
    {
        $statement = $this->pdo->query(
            'SELECT
                p.id,
                p.code,
                p.format,
                s.domain,
                pub.name AS publisher_name
             FROM placements p
             INNER JOIN sites s ON s.id = p.site_id
             INNER JOIN publishers pub ON pub.id = s.publisher_id
             ORDER BY s.domain, p.code'
        );

        return $statement->fetchAll();
    }
}

