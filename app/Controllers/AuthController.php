<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\Controller;
use App\Repositories\UserRepository;

final class AuthController extends Controller
{
    /** GET: formulário de login local */
    public function login(): void
    {
        $error = isset($_GET['e']) ? (string)$_GET['e'] : null;
        $this->view('auth/login', ['error' => $error]);
    }

    /** POST: autenticação local (email + senha) */
    /*public function loginPost(): void
    {
        $email = (string)($_POST['email'] ?? '');
        $pass  = (string)($_POST['password'] ?? '');

        $repo = new UserRepository();
        $user = $repo->verifyLocalLogin($email, $pass);

        if (!$user) {
            // volta com erro
            header('Location: /auth/login?e=1');
            exit;
        }

        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id'    => (int)$user['id'],
            'name'  => (string)$user['name'],
            'email' => (string)$user['email'],
            //'entra_object_id' => (string)($user['entra_object_id'] ?? ''),
            'role'  => (string)($user['role'] ?? 'user'),
        ];
        $repo->updateLastLogin((int)$user['id']);

        header('Location: /operations');
        exit;
    }*/

    public function loginPost(): void
    {
        $email = trim((string)($_POST['email'] ?? ''));
        $pass  = (string)($_POST['password'] ?? '');

        $repo = new \App\Repositories\UserRepository();
        $user = $repo->verifyLocalLogin($email, $pass);

        if (!$user) {
            $_SESSION['flash_error'] = 'Credenciais inválidas.';
            header('Location: /auth/local');
            exit;
        }

        // Regenera ID p/ evitar fixation e grava dados mínimos + role
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id'    => (int)$user['id'],
            'name'  => (string)$user['name'],
            'email' => (string)$user['email'],
            'role'  => (string)($user['role'] ?? 'user'),
        ];
        $repo->updateLastLogin((int)$user['id']);

        header('Location: /operations'); // ou outra landing
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
        // Exibe form e flash (erro) quando houver
        $error = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_error']);
        $this->view('auth/local', ['error' => $error]);
    }
}
