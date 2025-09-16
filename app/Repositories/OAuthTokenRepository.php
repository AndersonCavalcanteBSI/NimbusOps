<?php

declare(strict_types=1);

namespace App\Repositories;

use Core\Database;

final class OAuthTokenRepository
{
    public function upsert(
        int $userId,
        string $provider,
        string $accessToken,
        ?string $refreshToken,            // <-- agora pode ser null
        int|string $expiresIn,            // <-- aceita int ou string
        ?string $scope = null,
        ?string $tenantId = null
    ): void {
        $pdo = Database::pdo();

        // Normaliza expiração (aceita string/int e aplica margem de segurança)
        $expiresInSec = (int)$expiresIn;
        if ($expiresInSec <= 0) {
            $expiresInSec = 3600; // fallback 1h
        }
        $expiresAt = date('Y-m-d H:i:s', time() + max(0, $expiresInSec - 60)); // -60s de margem

        $sql = 'INSERT INTO oauth_tokens (user_id, provider, access_token, refresh_token, expires_at, scope, tenant_id)
                VALUES (:u,:p,:at,:rt,:ea,:sc,:tid)
                ON DUPLICATE KEY UPDATE
                    access_token = VALUES(access_token),
                    refresh_token = VALUES(refresh_token),
                    expires_at   = VALUES(expires_at),
                    scope        = VALUES(scope),
                    tenant_id    = VALUES(tenant_id)';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':u'   => $userId,
            ':p'   => $provider,
            ':at'  => $accessToken,
            ':rt'  => $refreshToken ?? '',   // <-- trata null
            ':ea'  => $expiresAt,
            ':sc'  => $scope,
            ':tid' => $tenantId,
        ]);
    }

    public function findActiveByUser(int $userId, string $provider = 'microsoft'): ?array
    {
        $pdo = Database::pdo();
        $st = $pdo->prepare('SELECT * FROM oauth_tokens WHERE user_id = :u AND provider = :p LIMIT 1');
        $st->execute([':u' => $userId, ':p' => $provider]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function deleteByUser(int $userId, string $provider = 'microsoft'): void
    {
        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM oauth_tokens WHERE user_id = :u AND provider = :p')
            ->execute([':u' => $userId, ':p' => $provider]);
    }

    public function findForUser(int $userId, string $provider = 'microsoft'): ?array
    {
        $pdo = \Core\Database::pdo();
        $st = $pdo->prepare('SELECT * FROM oauth_tokens WHERE user_id = :u AND provider = :p LIMIT 1');
        $st->execute([':u' => $userId, ':p' => $provider]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function isConnected(int $userId, string $provider = 'microsoft'): bool
    {
        $row = $this->findForUser($userId, $provider);
        // Considera conectado se tiver refresh_token (mesmo que access_token esteja expirado)
        return $row && !empty($row['refresh_token']);
    }
}
