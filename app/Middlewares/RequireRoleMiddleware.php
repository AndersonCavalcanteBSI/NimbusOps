<?php

declare(strict_types=1);

namespace App\Middlewares;

use Core\Middleware;

final class RequireRoleMiddleware implements Middleware
{
    /** @param string[] $allowed */
    public function __construct(private array $allowed) {}

    public function handle(): void
    {
        $role = $_SESSION['user']['role'] ?? null;
        if (!$role || !in_array($role, $this->allowed, true)) {
            http_response_code(403);
            echo 'Forbidden (sem permissão).';
            exit;
        }
    }
}
