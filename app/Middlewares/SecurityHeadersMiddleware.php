<?php

declare(strict_types=1);


namespace App\Middlewares;


use Core\Middleware;


final class SecurityHeadersMiddleware implements Middleware
{
    public function handle(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: no-referrer-when-downgrade');
        header('X-XSS-Protection: 0');
        header('Permissions-Policy: geolocation=(), microphone=()');
        header("Content-Security-Policy: default-src 'self' 'unsafe-inline' https: data:");
    }
}
