<?php

declare(strict_types=1);

namespace App\Repositories;

use Core\Database;

final class MeasurementReviewRepository
{
    /*public function createStage(int $fileId, int $stage, int $reviewerUserId): void
    {
        $pdo = Database::pdo();
        $st = $pdo->prepare(
            'INSERT INTO measurement_reviews (measurement_file_id, stage, reviewer_user_id) VALUES (:f,:s,:u)'
        );
        $st->execute([':f' => $fileId, ':s' => $stage, ':u' => $reviewerUserId]);
    }*/

    public function createStage(int $fileId, int $stage, int $reviewerUserId): void
    {
        if ($reviewerUserId <= 0) {
            throw new \InvalidArgumentException(
                "Tentativa de criar etapa {$stage} sem um revisor válido para o arquivo {$fileId}"
            );
        }

        $pdo = Database::pdo();
        $st = $pdo->prepare(
            'INSERT INTO measurement_reviews (measurement_file_id, stage, reviewer_user_id, status)
         VALUES (:f, :s, :u, "pending")
         ON DUPLICATE KEY UPDATE reviewer_user_id = VALUES(reviewer_user_id), status = "pending"'
        );
        $st->execute([':f' => $fileId, ':s' => $stage, ':u' => $reviewerUserId]);
    }

    /*public function getStage(int $fileId, int $stage): ?array
    {
        $pdo = Database::pdo();
        $st = $pdo->prepare(
            'SELECT * FROM measurement_reviews WHERE measurement_file_id = :f AND stage = :s LIMIT 1'
        );
        $st->execute([':f' => $fileId, ':s' => $stage]);
        $r = $st->fetch();
        return $r ?: null;
    }*/

    public function getStage(int $fileId, int $stage): ?array
    {
        $pdo = Database::pdo();
        $st = $pdo->prepare(
            'SELECT * FROM measurement_reviews
         WHERE measurement_file_id = :f AND stage = :s
         ORDER BY id DESC
         LIMIT 1'
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

    public function listByFile(int $fileId): array
    {
        $pdo = \Core\Database::pdo();
        $st = $pdo->prepare(
            'SELECT measurement_reviews.*, users.name AS reviewer_name, users.email AS reviewer_email
         FROM measurement_reviews
         LEFT JOIN users ON users.id = measurement_reviews.reviewer_user_id
         WHERE measurement_file_id = :f
         ORDER BY stage ASC'
        );
        $st->execute([':f' => $fileId]);
        return $st->fetchAll();
    }

    public function nextPendingStage(int $fileId): ?int
    {
        $pdo = \Core\Database::pdo();
        $st = $pdo->prepare(
            'SELECT stage
           FROM measurement_reviews
          WHERE measurement_file_id = :f
            AND status = "pending"
          ORDER BY stage ASC
          LIMIT 1'
        );
        $st->execute([':f' => $fileId]);
        $s = $st->fetchColumn();
        return $s ? (int)$s : null;
    }
}
