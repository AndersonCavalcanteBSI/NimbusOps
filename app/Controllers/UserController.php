<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\Controller;
use App\Repositories\UserRepository;
use App\Repositories\OAuthTokenRepository;

final class UserController extends Controller
{
    /*public function index(): void
    {
        $users = (new UserRepository())->listAll();
        $msgOk = $_SESSION['flash_ok']   ?? null;
        $msgEr = $_SESSION['flash_err']  ?? null;
        unset($_SESSION['flash_ok'], $_SESSION['flash_err']);

        $this->view('users/index', [
            'users'   => $users,
            'ok'      => $msgOk,
            'error'   => $msgEr,
        ]);
    }*/
    public function index(): void
    {
        $filters = [
            'q'      => trim((string)($_GET['q'] ?? '')),
            'role'   => trim((string)($_GET['role'] ?? '')),
            'active' => ($_GET['active'] ?? '') !== '' ? (int)$_GET['active'] : null, // null|0|1
        ];

        $page = max(1, (int)($_GET['page'] ?? 1));
        $per  = min(50, max(5, (int)($_GET['per'] ?? 20)));

        $repo   = new UserRepository();
        $result = $repo->paginateFiltered($filters, $page, $per, 'name', 'asc');

        $msgOk = $_SESSION['flash_ok']  ?? null;
        $msgEr = $_SESSION['flash_err'] ?? null;
        unset($_SESSION['flash_ok'], $_SESSION['flash_err']);

        $this->view('users/index', [
            'users'  => $result['data'],
            'total'  => $result['total'],
            'page'   => $result['page'],
            'per'    => $result['per_page'],
            'ok'     => $msgOk,
            'error'  => $msgEr,
        ]);
    }

    public function create(): void
    {
        $this->view('users/form', [
            'user' => null,          // form de criação
            // OPCIONAL (evita undefined na view)
            'msConnected' => false,
            'msToken'     => null,
        ]);
    }

    public function store(): void
    {
        $name  = trim((string)($_POST['name']  ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $role  = (string)($_POST['role'] ?? 'user');
        $active = isset($_POST['active']) ? true : false;
        $pass   = (string)($_POST['password'] ?? '');

        if ($name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_err'] = 'Nome e e-mail válidos são obrigatórios.';
            header('Location: /users/create');
            exit;
        }
        if ($pass !== '' && strlen($pass) < 6) {
            $_SESSION['flash_err'] = 'Senha deve ter ao menos 6 caracteres.';
            header('Location: /users/create');
            exit;
        }

        $repo = new UserRepository();
        try {
            $id = $repo->createFull($name, $email, $pass !== '' ? $pass : null, $role, $active);
            $_SESSION['flash_ok'] = 'Usuário criado com sucesso.';
            header('Location: /users');
            exit;
        } catch (\PDOException $e) {
            $_SESSION['flash_err'] = 'Não foi possível salvar. E-mail já cadastrado?';
            header('Location: /users/create');
            exit;
        }
    }

    public function edit(int $id): void
    {
        $u = (new UserRepository())->findFull($id);
        if (!$u) {
            http_response_code(404);
            echo 'Usuário não encontrado';
            return;
        }

        // ADIÇÃO — consulta o vínculo Microsoft no banco
        $tokRepo     = new OAuthTokenRepository();
        $msToken     = $tokRepo->findForUser($id, 'microsoft');
        $msConnected = $tokRepo->isConnected($id, 'microsoft');

        $this->view('users/form', [
            'user'        => $u,
            'msConnected' => $msConnected,
            'msToken'     => $msToken,
        ]);
    }

    public function update(int $id): void
    {
        $repo  = new UserRepository();
        $u     = $repo->findFull($id);
        if (!$u) {
            http_response_code(404);
            echo 'Usuário não encontrado';
            return;
        }

        $name   = trim((string)($_POST['name']  ?? ''));
        $email  = trim((string)($_POST['email'] ?? ''));
        $role   = (string)($_POST['role'] ?? 'user');
        $active = isset($_POST['active']) ? true : false;
        $pass   = (string)($_POST['password'] ?? '');

        if ($name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_err'] = 'Nome e e-mail válidos são obrigatórios.';
            header('Location: /users/' . $id . '/edit');
            exit;
        }
        if ($pass !== '' && strlen($pass) < 6) {
            $_SESSION['flash_err'] = 'Senha deve ter ao menos 6 caracteres.';
            header('Location: /users/' . $id . '/edit');
            exit;
        }

        try {
            $repo->updateProfile($id, $name, $email, $role, $active);
            if ($pass !== '') {
                $repo->setPassword($id, $pass);
            }
            $_SESSION['flash_ok'] = 'Usuário atualizado.';
            header('Location: /users');
            exit;
        } catch (\PDOException $e) {
            $_SESSION['flash_err'] = 'Não foi possível salvar. E-mail já cadastrado?';
            header('Location: /users/' . $id . '/edit');
            exit;
        }
    }
}
