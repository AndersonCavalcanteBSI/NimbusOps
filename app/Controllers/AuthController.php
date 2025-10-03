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

        // Regenera ID p/ evitar fixation e grava dados
        session_regenerate_id(true);

        // highlight-start
        // ===== CORREÇÃO APLICADA AQUI =====
        // 1. Salva o ID do usuário na chave esperada pela aplicação.
        $_SESSION['user_id'] = (int)$user['id'];

        // 2. Salva o restante dos dados em um array separado, como antes.
        $_SESSION['user'] = [
            'id'              => (int)$user['id'],
            'name'            => (string)$user['name'],
            'email'           => (string)$user['email'],
            'role'            => (string)($user['role'] ?? 'user'),
            'entra_object_id' => (string)($user['entra_object_id'] ?? ''),
            'ms_linked'       => (int)($user['ms_linked'] ?? 0),
        ];

        // >>> ADIÇÃO: avatar + versão para bust de cache
        $avatar = (string)($user['avatar'] ?? '');
        $_SESSION['user']['avatar'] = $avatar;
        $_SESSION['user']['avatar_ver'] = '';
        if ($avatar !== '') {
            $docroot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? (dirname(__DIR__, 2) . '/public'), '/');
            $fsPath  = $docroot . $avatar; // ex.: /var/www/html/public/uploads/avatars/10/avatar.png
            if (is_file($fsPath)) {
                $_SESSION['user']['avatar_ver'] = (string) filemtime($fsPath);
            } else {
                // fallback: força recarregar na primeira sessão
                $_SESSION['user']['avatar_ver'] = (string) time();
            }
        }
        // highlight-end

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
        $provider = $this->provider();

        // Detecta intenção com base na sessão
        $isLogged = !empty($_SESSION['user']['id']);
        $_SESSION['ms_link_intent'] = $isLogged ? 'link' : 'login';
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
        // valida state anti-CSRF
        $got = (string)($_GET['state'] ?? '');
        $exp = (string)($_SESSION['ms_link_state'] ?? '');
        unset($_SESSION['ms_link_state']);
        if ($got === '' || $exp === '' || !hash_equals($exp, $got)) {
            http_response_code(400);
            echo 'Estado inválido. Tente novamente.';
            return;
        }

        $intent   = (string)($_SESSION['ms_link_intent'] ?? 'login');
        unset($_SESSION['ms_link_intent']);

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

        $repo   = new UserRepository();
        $tokRepo = new OAuthTokenRepository();

        if ($intent === 'link') {
            // Precisa estar logado para vincular
            if (empty($_SESSION['user']['id'])) {
                header('Location: /auth/local');
                exit;
            }

            $userId = (int)$_SESSION['user']['id'];

            // evita que a mesma conta seja usada por outro usuário
            $other = $repo->findByEntraIdActive($entraId);
            if ($other && (int)$other['id'] !== $userId) {
                http_response_code(409);
                echo 'Esta conta Microsoft já está vinculada a outro usuário.';
                return;
            }

            // vincula + salva tokens
            $this->persistMsTokensAndLink($repo, $tokRepo, $token, $claims, $userId, $entraId);

            $_SESSION['user']['entra_object_id'] = $entraId;
            $_SESSION['user']['ms_linked']       = 1;

            $_SESSION['flash_success'] = 'Conta Microsoft conectada com sucesso.';
            session_write_close();
            header('Location: /profile');
            exit;
        }

        // ===== LOGIN COM MICROSOFT =====
        // Tenta achar por entra_object_id
        $user = $repo->findByEntraIdActive($entraId);

        // Se não achar, pode optar por procurar por e-mail corporativo
        if (!$user && $email !== '') {
            $user = $repo->findByEmailActive($email); // implemente caso ainda não exista
        }

        if (!$user) {
            // Política: não cria usuário automaticamente. Ajuste se quiser criar.
            http_response_code(403);
            echo 'Sua conta não está cadastrada. Contate o administrador.';
            return;
        }

        // Garante vínculo entraId no cadastro, se ainda não tiver
        if (empty($user['entra_object_id'])) {
            $repo->attachEntraId((int)$user['id'], $entraId);
        }

        // persiste tokens e marca ms_linked
        $this->persistMsTokensAndLink($repo, $tokRepo, $token, $claims, (int)$user['id'], $entraId);

        // cria sessão como no login local
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['user'] = [
            'id'              => (int)$user['id'],
            'name'            => (string)$user['name'],
            'email'           => (string)$user['email'],
            'role'            => (string)($user['role'] ?? 'user'),
            'entra_object_id' => (string)$entraId,
            'ms_linked'       => 1,
        ];

        // >>> ADIÇÃO: avatar + versão
        $avatar = (string)($user['avatar'] ?? '');
        $_SESSION['user']['avatar'] = $avatar;
        $_SESSION['user']['avatar_ver'] = '';
        if ($avatar !== '') {
            $docroot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? (dirname(__DIR__, 2) . '/public'), '/');
            $fsPath  = $docroot . $avatar;
            if (is_file($fsPath)) {
                $_SESSION['user']['avatar_ver'] = (string) filemtime($fsPath);
            } else {
                $_SESSION['user']['avatar_ver'] = (string) time();
            }
        }

        $repo->updateLastLogin((int)$user['id']);

        session_write_close();
        header('Location: /operations');
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
        header('Location: /profile');
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

    private function persistMsTokensAndLink(
        UserRepository $repo,
        OAuthTokenRepository $tokRepo,
        \League\OAuth2\Client\Token\AccessTokenInterface $token,
        array $claims,
        int $userId,
        string $entraId
    ): void {
        // vincula entraId
        $repo->attachEntraId($userId, $entraId);

        $values   = (array)($token->getValues() ?? []);
        $access   = (string)$token->getToken();
        $refresh  = (string)($token->getRefreshToken() ?? ($values['refresh_token'] ?? ''));
        $expiresIn = 0;
        $expEpoch  = (int)($token->getExpires() ?? 0);
        if ($expEpoch > 0) {
            $expiresIn = max(0, $expEpoch - time());
        }
        if ($expiresIn <= 0) {
            $expiresIn = (int)($values['expires_in'] ?? $values['ext_expires_in'] ?? 3600);
        }
        if (isset($values['scope'])) {
            $scopeStr = is_array($values['scope']) ? implode(' ', $values['scope']) : (string)$values['scope'];
        } elseif (isset($values['scp'])) {
            $scopeStr = (string)$values['scp'];
        } else {
            $scopeStr = 'openid profile email offline_access User.Read';
        }
        $tenantId = (string)($claims['tid'] ?? $_ENV['GRAPH_TENANT_ID'] ?? 'common');

        $tokRepo->upsert($userId, 'microsoft', $access, $refresh, $expiresIn, $scopeStr, $tenantId);

        \Core\Database::pdo()
            ->prepare('UPDATE users SET ms_linked = 1 WHERE id = :id')
            ->execute([':id' => $userId]);
    }
}
