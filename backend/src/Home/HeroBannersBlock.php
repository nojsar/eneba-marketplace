<?php

declare(strict_types=1);

namespace App\Home;

use App\Database;

/** The banner area at the top: content comes from the banners table. */
final class HeroBannersBlock implements BlockProvider
{
    public function __construct(private readonly Database $db)
    {
    }

    public function component(): string
    {
        return 'hero';
    }

    public function content(): array
    {
        $rows = $this->db->pdo()->query(
            'SELECT title, subtitle, image_url, cta_label, cta_url
               FROM banners
              WHERE is_active
              ORDER BY position
              LIMIT 4'
        )->fetchAll();

        return [
            'banners' => array_map(fn (array $row) => [
                'title' => $row['title'],
                'subtitle' => $row['subtitle'],
                'imageUrl' => $row['image_url'],
                'ctaLabel' => $row['cta_label'],
                'ctaUrl' => $row['cta_url'],
            ], $rows),
        ];
    }
}
