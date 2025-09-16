<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\Controller;
use App\Repositories\UserRepository;
use App\Repositories\OAuthTokenRepository;
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

        // >>> AJUSTE: reforça ms_linked a partir da tabela oauth_tokens (persiste após logout)
        $tokRepo = new OAuthTokenRepository();
        if ($tokRepo->isConnected((int)$user['id'], 'microsoft')) {
            $_SESSION['user']['ms_linked'] = 1;
        }

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

        // intenção padrão: apenas vincular a conta
        $_SESSION['ms_link_intent'] = 'link';
        $_SESSION['ms_link_state']  = bin2hex(random_bytes(32));

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

        // valida state anti-CSRF
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
            $email   = (string)($claims['mail']
                ?? $claims['userPrincipalName']
                ?? $owner->claim('preferred_username')
                ?? '');
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

        $userId = (int)$_SESSION['user']['id'];
        $repo   = new UserRepository();

        // Evita que a mesma conta MS seja usada por outro usuário ativo
        $other = $repo->findByEntraIdActive($entraId);
        if ($other && (int)$other['id'] !== $userId) {
            http_response_code(409);
            echo 'Esta conta Microsoft já está vinculada a outro usuário.';
            return;
        }

        // 1) Vincula a conta Microsoft ao usuário
        $repo->attachEntraId($userId, $entraId);

        // 2) Persiste tokens OAuth (access/refresh/expires/scope/tenant)
        $values = (array)($token->getValues() ?? []);

        // access token
        $access = (string)$token->getToken();

        // refresh token — pode vir em $token->getRefreshToken() ou em $values['refresh_token']
        $refresh = (string)($token->getRefreshToken() ?? ($values['refresh_token'] ?? ''));

        // expiresIn — calcula com base em getExpires() (epoch) ou em 'expires_in'/'ext_expires_in'
        $expiresIn = 0;
        $expEpoch  = (int)($token->getExpires() ?? 0);
        if ($expEpoch > 0) {
            $expiresIn = max(0, $expEpoch - time());
        }
        if ($expiresIn <= 0) {
            $expiresIn = (int)($values['expires_in'] ?? $values['ext_expires_in'] ?? 3600);
        }

        // scope — pode vir como string em 'scope' / 'scp' ou como array
        if (isset($values['scope'])) {
            $scopeStr = is_array($values['scope']) ? implode(' ', $values['scope']) : (string)$values['scope'];
        } elseif (isset($values['scp'])) { // Azure às vezes retorna 'scp'
            $scopeStr = (string)$values['scp'];
        } else {
            $scopeStr = 'openid profile email offline_access User.Read';
        }

        // tenant
        $tenantId = (string)($claims['tid'] ?? $_ENV['GRAPH_TENANT_ID'] ?? 'common');

        // upsert na tabela oauth_tokens (provider = "microsoft")
        (new OAuthTokenRepository())->upsert(
            $userId,
            'microsoft',
            $access,
            $refresh,
            $expiresIn,
            $scopeStr,
            $tenantId
        );

        // marca usuário como "ms_linked"
        \Core\Database::pdo()
            ->prepare('UPDATE users SET ms_linked = 1 WHERE id = :id')
            ->execute([':id' => $userId]);

        // atualiza sessão
        $_SESSION['user']['entra_object_id'] = $entraId;
        $_SESSION['user']['ms_linked']       = 1;

        $_SESSION['flash_success'] = 'Conta Microsoft conectada com sucesso.';
        session_write_close();

        // intenção (se no futuro houver "login")
        $intent = (string)($_SESSION['ms_link_intent'] ?? 'link');
        unset($_SESSION['ms_link_intent']);

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

        $userId = (int)$_SESSION['user']['id'];

        // >>> AJUSTE: remove vínculo + tokens persistidos e zera flag no banco
        (new UserRepository())->detachEntraId($userId);
        (new OAuthTokenRepository())->deleteByUser($userId, 'microsoft');
        \Core\Database::pdo()
            ->prepare('UPDATE users SET ms_linked = 0 WHERE id = :id')
            ->execute([':id' => $userId]);

        // Atualiza sessão
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
