<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\Controller;
use App\Repositories\OperationRepository;
use App\Repositories\MeasurementFileRepository;

final class MeasurementController extends Controller
{
    /** Formulário: selecionar operação + enviar arquivo */
    public function create(): void
    {
        // Carrega até 200 operações para o select (pode trocar por busca dinâmica depois)
        $ops = (new OperationRepository())->paginate([], 1, 200, 'title', 'asc')['data'] ?? [];
        $this->view('measurements/upload', ['operations' => $ops]);
    }

    /** Recebe o POST do upload */
    public function store(): void
    {
        $opId = (int)($_POST['operation_id'] ?? 0);
        if ($opId <= 0) {
            http_response_code(400);
            echo 'Operação inválida';
            return;
        }

        // Confirma se a operação existe
        $op = (new OperationRepository())->find($opId);
        if (!$op) {
            http_response_code(404);
            echo 'Operação não encontrada';
            return;
        }

        // Validação do arquivo
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo 'Arquivo inválido';
            return;
        }

        $name = (string)$_FILES['file']['name'];
        $tmp  = (string)$_FILES['file']['tmp_name'];
        $size = (int)$_FILES['file']['size'];
        if ($size > 20 * 1024 * 1024) {
            http_response_code(400);
            echo 'Arquivo muito grande (max 20MB)';
            return;
        }

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $allowed = ['pdf', 'xlsx', 'xls', 'csv'];
        if (!in_array($ext, $allowed, true)) {
            http_response_code(400);
            echo 'Tipo não permitido';
            return;
        }

        // Salva em public/uploads/ops/{operation_id}/
        $dir = __DIR__ . '/../../public/uploads/ops/' . $opId;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            http_response_code(500);
            echo 'Falha ao criar diretório';
            return;
        }

        $safeBase = pathinfo($name, PATHINFO_FILENAME);
        $safeBase = preg_replace('/[^a-zA-Z0-9_-]+/', '_', (string)$safeBase) ?: 'medicao';
        $new = $safeBase . '_' . date('Ymd_His') . '.' . $ext;
        $dest = $dir . '/' . $new;
        if (!move_uploaded_file($tmp, $dest)) {
            http_response_code(500);
            echo 'Falha ao salvar';
            return;
        }

        // Persiste registro
        $publicPath = '/uploads/ops/' . $opId . '/' . $new; // acessível via web
        (new MeasurementFileRepository())->create($opId, $name, $publicPath, null);

        // Redireciona (com flag de sucesso)
        header('Location: /measurements/upload?ok=1');
    }
}
