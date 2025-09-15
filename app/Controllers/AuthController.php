<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\Controller;
use App\Repositories\UserRepository;
use TheNetworg\OAuth2\Client\Provider\Azure;

final class AuthController extends Controller
{
    public function login(): void
    {
        $provider = $this->provider();

        // Gera e fixa o state manualmente
        $_SESSION['oauth2state'] = bin2hex(random_bytes(32));

        $authUrl = $provider->getAuthorizationUrl([
            'scope'  => ['openid', 'profile', 'email', 'offline_access', 'User.Read'],
            'prompt' => 'select_account',
            'state'  => $_SESSION['oauth2state'], // <== usando o nosso state
        ]);

        header('Location: ' . $authUrl);
        exit;
    }


    public function callback(): void
    {
        // DEPURAÇÃO OPCIONAL EM DESENVOLVIMENTO
        $isLocal = ($_ENV['APP_ENV'] ?? 'local') === 'local';
        if ($isLocal) {
            error_log('OAUTH state GET=' . ($_GET['state'] ?? '[n/a]') .
                ' SESSION=' . ($_SESSION['oauth2state'] ?? '[n/a]'));
        }

        // Checagem robusta do state
        $got = (string)($_GET['state'] ?? '');
        $exp = (string)($_SESSION['oauth2state'] ?? '');
        if ($got === '' || $exp === '' || !hash_equals($exp, $got)) {
            unset($_SESSION['oauth2state']);
            http_response_code(400);
            echo 'Estado inválido.';
            return;
        }
        // Invalida o state após uso (evita replay)
        unset($_SESSION['oauth2state']);

        $provider = $this->provider();

        try {
            $token  = $provider->getAccessToken('authorization_code', ['code' => $_GET['code'] ?? '']);
            $owner  = $provider->getResourceOwner($token);
            $claims = $owner->toArray();

            $entraId = (string)($owner->getId() ?? '');
            $email   = (string)($claims['mail'] ?? $claims['userPrincipalName'] ?? $owner->claim('preferred_username') ?? '');
            $name    = (string)($claims['displayName'] ?? $owner->claim('name') ?? '');
        } catch (\Throwable $e) {
            http_response_code(500);
            echo 'Erro ao autenticar com Microsoft.';
            return;
        }

        // Política: só entra se EMAIL estiver na tabela users e active=1
        $userRepo = new UserRepository();
        $user = $email ? $userRepo->findByEmailActive($email) : null;

        if (!$user) {
            http_response_code(403);
            echo 'Acesso não autorizado. Seu e-mail não está habilitado no sistema.';
            return;
        }

        // Atualiza o entra_object_id se estiver vazio
        if (empty($user['entra_object_id']) && $entraId) {
            $userRepo->attachEntraId((int)$user['id'], $entraId);
            $user['entra_object_id'] = $entraId;
        }

        // Evita fixation e grava a sessão do usuário
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id'    => (int)$user['id'],
            'name'  => (string)$user['name'],
            'email' => (string)$user['email'],
            'entra_object_id' => (string)($user['entra_object_id'] ?? ''),
        ];

        header('Location: /operations'); // landing após login
        exit;
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        header('Location: /auth/login');
        exit;
    }

    private function provider(): Azure
    {
        $tenant       = $_ENV['GRAPH_TENANT_ID'] ?? 'common';
        $clientId     = $_ENV['GRAPH_CLIENT_ID'] ?? '';
        $clientSecret = $_ENV['GRAPH_CLIENT_SECRET'] ?? '';
        $redirectUri  = $_ENV['GRAPH_REDIRECT_URI'] ?? '';

        return new Azure([
            'clientId'                => $clientId,
            'clientSecret'            => $clientSecret,
            'redirectUri'             => $redirectUri,
            'tenant'                  => $tenant,
            // Opcional: `'defaultEndPointVersion' => '2.0'`
        ]);
    }
}
