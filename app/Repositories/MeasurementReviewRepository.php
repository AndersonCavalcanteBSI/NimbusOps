<?php

declare(strict_types=1);

namespace App\Repositories;

use Core\Database;

final class MeasurementReviewRepository
{
    public function createStage(int $fileId, int $stage, int $reviewerUserId): void
    {
        $pdo = Database::pdo();
        $st = $pdo->prepare(
            'INSERT INTO measurement_reviews (measurement_file_id, stage, reviewer_user_id) VALUES (:f,:s,:u)'
        );
        $st->execute([':f' => $fileId, ':s' => $stage, ':u' => $reviewerUserId]);
    }

    public function getStage(int $fileId, int $stage): ?array
    {
        $pdo = Database::pdo();
        $st = $pdo->prepare(
            'SELECT * FROM measurement_reviews WHERE measurement_file_id = :f AND stage = :s LIMIT 1'
        );
        $st->execute([':f' => $fileId, ':s' => $stage]);
        $r = $st->fetch();
        return $r ?: null;
    }

    public function decide(int $fileId, int $stage, string $decision, string $notes): void
    {
        $pdo = Database::pdo();
        $st = $pdo->prepare(
            'UPDATE measurement_reviews SET status = :d, notes = :n, reviewed_at = NOW()
             WHERE measurement_file_id = :f AND stage = :s'
        );
        $st->execute([':d' => $decision, ':n' => $notes, ':f' => $fileId, ':s' => $stage]);
    }
}
