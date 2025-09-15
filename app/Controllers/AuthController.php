<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\Controller;
use App\Repositories\UserRepository;
use TheNetworg\OAuth2\Client\Provider\Azure;

final class AuthController extends Controller
{
    /** GET: formulário de login local */
    public function login(): void
    {
        $error = isset($_GET['e']) ? (string)$_GET['e'] : null;
        $this->view('auth/login', ['error' => $error]);
    }

    /** POST: autenticação local (email + senha) */
    public function loginPost(): void
    {
        $email = trim((string)($_POST['email'] ?? ''));
        $pass  = (string)($_POST['password'] ?? '');

        $repo = new UserRepository();
        $user = $repo->verifyLocalLogin($email, $pass);

        if (!$user) {
            $_SESSION['flash_error'] = 'Credenciais inválidas.';
            session_write_close();
            header('Location: /auth/local');
            exit;
        }

        // Regenera ID p/ evitar fixation e grava dados mínimos + role + status de vínculo MS
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id'              => (int)$user['id'],
            'name'            => (string)$user['name'],
            'email'           => (string)$user['email'],
            'role'            => (string)($user['role'] ?? 'user'),
            'entra_object_id' => (string)($user['entra_object_id'] ?? ''),
            'ms_linked'       => (int)($user['ms_linked'] ?? 0),
        ];
        $repo->updateLastLogin((int)$user['id']);

        session_write_close();
        header('Location: /operations');
        exit;
    }

    /** INÍCIO do vínculo Microsoft (somente logado) */
    public function microsoftStart(): void
    {
        if (empty($_SESSION['user']['id'])) {
            header('Location: /auth/local');
            exit;
        }

        $provider = $this->provider();

        $_SESSION['ms_link_state'] = bin2hex(random_bytes(32));
        $authUrl = $provider->getAuthorizationUrl([
            'scope'  => ['openid', 'profile', 'email', 'offline_access', 'User.Read'],
            'prompt' => 'select_account',
            'state'  => $_SESSION['ms_link_state'],
        ]);

        header('Location: ' . $authUrl);
        exit;
    }

    /** CALLBACK do vínculo Microsoft */
    public function microsoftCallback(): void
    {
        if (empty($_SESSION['user']['id'])) {
            header('Location: /auth/local');
            exit;
        }

        // valida state
        $got = (string)($_GET['state'] ?? '');
        $exp = (string)($_SESSION['ms_link_state'] ?? '');
        unset($_SESSION['ms_link_state']);
        if ($got === '' || $exp === '' || !hash_equals($exp, $got)) {
            http_response_code(400);
            echo 'Estado inválido. Tente novamente.';
            return;
        }

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
            echo 'Falha ao autenticar com Microsoft.';
            return;
        }

        if ($entraId === '') {
            http_response_code(400);
            echo 'Não foi possível obter o identificador da conta Microsoft.';
            return;
        }

        $repo = new UserRepository();

        // Impede que a mesma conta MS seja usada por outro usuário ativo
        $other = $repo->findByEntraIdActive($entraId);
        if ($other && (int)$other['id'] !== (int)$_SESSION['user']['id']) {
            http_response_code(409);
            echo 'Esta conta Microsoft já está vinculada a outro usuário.';
            return;
        }

        // Vincula à conta atual
        $repo->attachEntraId((int)$_SESSION['user']['id'], $entraId);

        // Atualiza sessão
        $_SESSION['user']['entra_object_id'] = $entraId;
        $_SESSION['user']['ms_linked']       = 1;

        $_SESSION['flash_success'] = 'Conta Microsoft conectada com sucesso.';
        session_write_close();
        header('Location: /');
        exit;
    }

    /** DESVINCULAR Microsoft (somente logado) */
    public function unlinkMicrosoft(): void
    {
        if (empty($_SESSION['user']['id'])) {
            header('Location: /auth/local');
            exit;
        }

        (new UserRepository())->detachEntraId((int)$_SESSION['user']['id']);
        $_SESSION['user']['entra_object_id'] = '';
        $_SESSION['user']['ms_linked']       = 0;

        $_SESSION['flash_success'] = 'Conta Microsoft desvinculada.';
        session_write_close();
        header('Location: /');
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
        session_write_close();
        header('Location: /auth/local');
        exit;
    }

    public function localForm(): void
    {
        $error = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_error']);
        $this->view('auth/local', ['error' => $error]);
    }

    /** Provider do OAuth Microsoft (Entra ID) */
    private function provider(): Azure
    {
        return new Azure([
            'clientId'     => (string)($_ENV['GRAPH_CLIENT_ID'] ?? ''),
            'clientSecret' => (string)($_ENV['GRAPH_CLIENT_SECRET'] ?? ''),
            'redirectUri'  => (string)($_ENV['GRAPH_REDIRECT_URI'] ?? ''),
            'tenant'       => (string)($_ENV['GRAPH_TENANT_ID'] ?? 'common'),
        ]);
    }
}
