<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\Controller;
use App\Repositories\UserRepository;

final class ProfileController extends Controller
{
    private function requireLogin(): int
    {
        $uid = (int)($_SESSION['user_id'] ?? 0);
        if ($uid <= 0) {
            header('Location: /auth/local');
            exit;
        }
        return $uid;
    }

    /** GET /profile */
    /*public function show(): void
    {
        $uid  = $this->requireLogin();
        $user = (new UserRepository())->findBasic($uid);

        if (!$user) {
            http_response_code(404);
            echo 'Usuário não encontrado.';
            return;
        }

        $flashSuccess = $_SESSION['flash_success'] ?? null;
        $flashError   = $_SESSION['flash_error']   ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        $this->view('profile/index', [
            'user'         => $user,
            'flashSuccess' => $flashSuccess,
            'flashError'   => $flashError,
        ]);
    }*/
    public function show(): void
    {
        $uid  = $this->requireLogin();
        $user = (new UserRepository())->findBasic($uid);
        if (!$user) {
            http_response_code(404);
            echo 'Usuário não encontrado.';
            return;
        }

        // CSRF: um token para cada formulário
        $csrfProfile = bin2hex(random_bytes(16));
        $csrfPwd     = bin2hex(random_bytes(16));
        $_SESSION['csrf_profile'] = $csrfProfile;
        $_SESSION['csrf_pwd']     = $csrfPwd;

        $flashSuccess = $_SESSION['flash_success'] ?? null;
        $flashError   = $_SESSION['flash_error']   ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        $this->view('profile/index', [
            'user'         => $user,
            'flashSuccess' => $flashSuccess,
            'flashError'   => $flashError,
            'csrf_profile' => $csrfProfile,   // ← use na view, form de dados pessoais
            'csrf_pwd'     => $csrfPwd,       // ← use na view, form de senha
        ]);
    }

    /** POST /profile — atualiza nome e (opcional) senha e avatar */
    /*public function update(): void
    {
        $uid = $this->requireLogin();

        $name     = trim((string)($_POST['name'] ?? ''));
        $curPass  = (string)($_POST['current_password'] ?? '');
        $newPass  = (string)($_POST['new_password'] ?? '');
        $newPass2 = (string)($_POST['new_password_confirm'] ?? '');

        if ($name === '') {
            $_SESSION['flash_error'] = 'Informe seu nome.';
            header('Location: /profile');
            exit;
        }

        $repo = new UserRepository();

        // 1) Atualiza nome
        try {
            $repo->updateProfile($uid, ['name' => $name]);
        } catch (\Throwable) {
            $_SESSION['flash_error'] = 'Falha ao atualizar o nome.';
            header('Location: /profile');
            exit;
        }

        // 2) (Opcional) Upload de avatar
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
            $err = (int)$_FILES['avatar']['error'];
            if ($err === UPLOAD_ERR_OK) {
                $tmp  = (string)$_FILES['avatar']['tmp_name'];
                $size = (int)$_FILES['avatar']['size'];
                $nameUp = (string)$_FILES['avatar']['name'];

                if ($size > 2 * 1024 * 1024) { // 2MB
                    $_SESSION['flash_error'] = 'Avatar muito grande (máx. 2MB).';
                    header('Location: /profile');
                    exit;
                }

                $ext = strtolower(pathinfo($nameUp, PATHINFO_EXTENSION));
                if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true)) {
                    $_SESSION['flash_error'] = 'Formato de avatar inválido (use PNG, JPG ou WEBP).';
                    header('Location: /profile');
                    exit;
                }

                $dir = __DIR__ . '/../../public/uploads/avatars/' . $uid;
                if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                    $_SESSION['flash_error'] = 'Falha ao criar diretório do avatar.';
                    header('Location: /profile');
                    exit;
                }

                // nome fixo (evita lixo de versões antigas): avatar.ext
                $fn = 'avatar.' . $ext;
                $path = $dir . '/' . $fn;
                if (!move_uploaded_file($tmp, $path)) {
                    $_SESSION['flash_error'] = 'Falha ao salvar avatar.';
                    header('Location: /profile');
                    exit;
                }

                // caminho público
                $public = '/uploads/avatars/' . $uid . '/' . $fn;
                try {
                    $repo->updateProfile($uid, ['avatar' => $public]);
                } catch (\Throwable) {
                    // não impede o restante
                }
            } else {
                $_SESSION['flash_error'] = 'Falha no upload do avatar.';
                header('Location: /profile');
                exit;
            }
        }

        // 3) (Opcional) Troca de senha
        if ($curPass !== '' || $newPass !== '' || $newPass2 !== '') {
            if ($newPass === '' || $newPass2 === '') {
                $_SESSION['flash_error'] = 'Informe e confirme a nova senha.';
                header('Location: /profile');
                exit;
            }
            if ($newPass !== $newPass2) {
                $_SESSION['flash_error'] = 'A confirmação da senha não confere.';
                header('Location: /profile');
                exit;
            }
            if (strlen($newPass) < 8) {
                $_SESSION['flash_error'] = 'A nova senha deve ter ao menos 8 caracteres.';
                header('Location: /profile');
                exit;
            }

            // valida senha atual
            $me = $repo->findBasic($uid);
            if (!$me || !$repo->verifyLocalLogin((string)$me['email'], $curPass)) {
                $_SESSION['flash_error'] = 'Senha atual incorreta.';
                header('Location: /profile');
                exit;
            }

            try {
                $repo->updatePassword($uid, $newPass);
            } catch (\Throwable) {
                $_SESSION['flash_error'] = 'Falha ao alterar a senha.';
                header('Location: /profile');
                exit;
            }
        }

        // Atualiza sessão com novo nome (e avatar, se mudou)
        $_SESSION['user']['name']   = $name;
        if (!empty($public ?? null)) {
            $_SESSION['user']['avatar'] = $public;
        }

        $_SESSION['flash_success'] = 'Perfil atualizado com sucesso.';
        header('Location: /profile');
        exit;
    }*/
    /** POST /profile — atualiza nome e avatar */
    public function update(): void
    {
        $uid = $this->requireLogin();

        // CSRF
        $sent = (string)($_POST['csrf_profile'] ?? '');
        $good = (string)($_SESSION['csrf_profile'] ?? '');
        unset($_SESSION['csrf_profile']);
        if ($sent === '' || $good === '' || !hash_equals($good, $sent)) {
            $_SESSION['flash_error'] = 'Sessão expirada. Recarregue a página e tente novamente.';
            header('Location: /profile');
            exit;
        }

        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') {
            $_SESSION['flash_error'] = 'Informe seu nome.';
            header('Location: /profile');
            exit;
        }

        $repo = new UserRepository();

        // Atualiza nome
        try {
            $repo->updateProfile($uid, ['name' => $name]);
        } catch (\Throwable) {
            $_SESSION['flash_error'] = 'Falha ao atualizar o nome.';
            header('Location: /profile');
            exit;
        }

        // Upload de avatar (opcional)
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
            $err = (int)$_FILES['avatar']['error'];
            if ($err === UPLOAD_ERR_OK) {
                $tmp    = (string)$_FILES['avatar']['tmp_name'];
                $size   = (int)$_FILES['avatar']['size'];
                $nameUp = (string)$_FILES['avatar']['name'];

                if ($size > 2 * 1024 * 1024) {
                    $_SESSION['flash_error'] = 'Avatar muito grande (máx. 2MB).';
                    header('Location: /profile');
                    exit;
                }

                $ext = strtolower(pathinfo($nameUp, PATHINFO_EXTENSION));
                if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true)) {
                    $_SESSION['flash_error'] = 'Formato de avatar inválido (use PNG, JPG ou WEBP).';
                    header('Location: /profile');
                    exit;
                }

                $dir = __DIR__ . '/../../public/uploads/avatars/' . $uid;
                if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                    $_SESSION['flash_error'] = 'Falha ao criar diretório do avatar.';
                    header('Location: /profile');
                    exit;
                }

                $fn   = 'avatar.' . $ext;
                $path = $dir . '/' . $fn;
                if (!move_uploaded_file($tmp, $path)) {
                    $_SESSION['flash_error'] = 'Falha ao salvar avatar.';
                    header('Location: /profile');
                    exit;
                }

                $public = '/uploads/avatars/' . $uid . '/' . $fn;

                // Persiste no banco (best effort)
                try {
                    $repo->updateProfile($uid, ['avatar' => $public]);
                } catch (\Throwable) {
                }

                // >>> ATUALIZA SESSÃO (garante array + cache-buster)
                if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
                    $_SESSION['user'] = [];
                }
                $_SESSION['user']['avatar']     = $public;
                $_SESSION['user']['avatar_ver'] = (string) time();  // força recarregar na navbar

            } else {
                $_SESSION['flash_error'] = 'Falha no upload do avatar.';
                header('Location: /profile');
                exit;
            }
        }

        // Atualiza sessão com novo nome
        $_SESSION['user']['name'] = $name;

        $_SESSION['flash_success'] = 'Perfil atualizado com sucesso.';
        header('Location: /profile');
        exit;
    }

    /** POST /profile/password — altera a senha */
    public function updatePassword(): void
    {
        $uid = $this->requireLogin();

        // CSRF
        $sent = (string)($_POST['csrf_pwd'] ?? '');
        $good = (string)($_SESSION['csrf_pwd'] ?? '');
        unset($_SESSION['csrf_pwd']);
        if ($sent === '' || $good === '' || !hash_equals($good, $sent)) {
            $_SESSION['flash_error'] = 'Sessão expirada. Recarregue a página e tente novamente.';
            header('Location: /profile');
            exit;
        }

        $curPass  = (string)($_POST['current_password'] ?? '');
        $newPass  = (string)($_POST['new_password'] ?? '');
        $newPass2 = (string)($_POST['new_password_confirm'] ?? '');

        if ($newPass === '' || $newPass2 === '') {
            $_SESSION['flash_error'] = 'Informe e confirme a nova senha.';
            header('Location:/profile');
            exit;
        }
        if ($newPass !== $newPass2) {
            $_SESSION['flash_error'] = 'A confirmação da senha não confere.';
            header('Location:/profile');
            exit;
        }
        if (strlen($newPass) < 8) {
            $_SESSION['flash_error'] = 'A nova senha deve ter ao menos 8 caracteres.';
            header('Location:/profile');
            exit;
        }

        $repo = new UserRepository();
        $me   = $repo->findBasic($uid);
        if (!$me || !$repo->verifyLocalLogin((string)$me['email'], $curPass)) {
            $_SESSION['flash_error'] = 'Senha atual incorreta.';
            header('Location:/profile');
            exit;
        }

        try {
            $repo->updatePassword($uid, $newPass);
            $_SESSION['flash_success'] = 'Senha alterada com sucesso.';
        } catch (\Throwable) {
            $_SESSION['flash_error'] = 'Falha ao alterar a senha.';
        }

        header('Location: /profile');
        exit;
    }
}
