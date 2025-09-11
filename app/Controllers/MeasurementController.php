<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\Controller;
use Core\Mailer;
use Core\Env;
use App\Repositories\OperationRepository;
use App\Repositories\OperationHistoryRepository;
use App\Repositories\MeasurementFileRepository;
use App\Repositories\MeasurementReviewRepository;
use App\Repositories\UserRepository;

final class MeasurementController extends Controller
{
    public function create(): void
    {
        $ops = (new OperationRepository())->paginate([], 1, 200, 'title', 'asc')['data'] ?? [];
        $this->view('measurements/upload', ['operations' => $ops]);
    }

    public function store(): void
    {
        $opId = (int)($_POST['operation_id'] ?? 0);
        if ($opId <= 0) {
            http_response_code(400);
            echo 'Operação inválida';
            return;
        }
        $op = (new OperationRepository())->find($opId);
        if (!$op) {
            http_response_code(404);
            echo 'Operação não encontrada';
            return;
        }

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

        $dir = __DIR__ . '/../../public/uploads/ops/' . $opId;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            http_response_code(500);
            echo 'Falha ao criar diretório';
            return;
        }
        $safeBase = preg_replace('/[^a-zA-Z0-9_-]+/', '_', pathinfo($name, PATHINFO_FILENAME)) ?: 'medicao';
        $new = $safeBase . '_' . date('Ymd_His') . '.' . $ext;
        if (!move_uploaded_file($tmp, $dir . '/' . $new)) {
            http_response_code(500);
            echo 'Falha ao salvar';
            return;
        }

        $publicPath = '/uploads/ops/' . $opId . '/' . $new;
        $fileId = (new MeasurementFileRepository())->create($opId, $name, $publicPath, null);

        // status -> pending (já existente)
        $pdo = \Core\Database::pdo();
        $pdo->prepare('UPDATE operations SET status = "pending" WHERE id = :id')->execute([':id' => $opId]);
        (new OperationHistoryRepository())->log($opId, 'status_changed', 'Arquivo de medição adicionado: operação marcada como pending.');

        // criar etapa 1 (revisor = responsável)
        $responsibleId = (int)($op['responsible_user_id'] ?? 0);
        if ($responsibleId > 0) {
            (new MeasurementReviewRepository())->createStage($fileId, 1, $responsibleId);
        }

        // notificar responsável com link para analisar
        if ($responsibleId > 0) {
            $u = (new UserRepository())->findBasic($responsibleId);
            if ($u) {
                $base = rtrim($_ENV['APP_URL'] ?? '', '/');
                $link = $base . '/measurements/' . $fileId . '/review';
                $subject = 'Nova medição para a operação #' . $opId;
                $html = '<p>Olá, ' . htmlspecialchars($u['name']) . '</p>'
                    . '<p>Um novo arquivo de medição foi adicionado à operação <strong>#' . $opId . '</strong> (' . htmlspecialchars($op['title']) . ').</p>'
                    . '<p>Status da operação: <strong>Pendente</strong>.</p>'
                    . '<p><a href="' . htmlspecialchars($link) . '">Clique aqui para analisar</a>.</p>';
                try {
                    \Core\Mailer::send($u['email'], $u['name'], $subject, $html);
                } catch (\Throwable) {
                }
            }
        }
        header('Location: /measurements/upload?ok=1');
        exit;
    }
    private function findOperationIdByFile(int $fileId): ?int
    {
        $pdo = \Core\Database::pdo();
        $st = $pdo->prepare('SELECT operation_id FROM measurement_files WHERE id = :id');
        $st->execute([':id' => $fileId]);
        $opId = $st->fetchColumn();
        return $opId ? (int)$opId : null;
    }

    /** GET: Mostra formulário de análise (estágio 1) */
    public function reviewForm(int $fileId): void
    {
        $pdo = \Core\Database::pdo();
        $st = $pdo->prepare('SELECT * FROM measurement_files WHERE id = :id LIMIT 1');
        $st->execute([':id' => $fileId]);
        $file = $st->fetch();
        if (!$file) {
            http_response_code(404);
            echo 'Arquivo não encontrado';
            return;
        }

        // carrega info da etapa 1
        $mrRepo = new \App\Repositories\MeasurementReviewRepository();
        $mr = $mrRepo->getStage($fileId, 1);
        if (!$mr) {
            // define revisor como responsável da operação
            $opStmt = $pdo->prepare('SELECT responsible_user_id FROM operations WHERE id = :op');
            $opStmt->execute([':op' => (int)$file['operation_id']]);
            $responsibleId = (int)($opStmt->fetchColumn() ?: 0);

            if ($responsibleId <= 0) {
                // fallback: escolha um usuário padrão (ex.: 1) ou mostre msg amigável
                $responsibleId = 1;
            }

            $mrRepo->createStage($fileId, 1, $responsibleId);
            $mr = $mrRepo->getStage($fileId, 1);
        }


        // (opcional) checar se o usuário atual é o revisor designado
        if (class_exists('\App\Security\CurrentUser')) {
            $me = \App\Security\CurrentUser::id();
            if ($me && $me !== (int)$mr['reviewer_user_id']) {
                http_response_code(403);
                echo 'Você não é o revisor desta etapa';
                return;
            }
        }

        $opId = $this->findOperationIdByFile($fileId);
        $this->view('measurements/review', [
            'file' => $file,
            'review' => $mr,
            'operationId' => $opId,
        ]);
    }

    /** POST: Recebe a decisão (Aprovar/Reprovar) com observações */
    public function reviewSubmit(int $fileId): void
    {
        $decision = ($_POST['decision'] ?? '') === 'approve' ? 'approved' : 'rejected';
        $notes = trim((string)($_POST['notes'] ?? ''));

        $mrRepo = new \App\Repositories\MeasurementReviewRepository();
        $opRepo = new \App\Repositories\OperationRepository();
        $ohRepo = new \App\Repositories\OperationHistoryRepository();
        $userRepo = new \App\Repositories\UserRepository();

        $stageRow = $mrRepo->getStage($fileId, 1);
        if (!$stageRow) {
            http_response_code(400);
            echo 'Etapa 1 não encontrada';
            return;
        }

        // (opcional) check revisor atual
        if (class_exists('\App\Security\CurrentUser')) {
            $me = \App\Security\CurrentUser::id();
            if ($me && $me !== (int)$stageRow['reviewer_user_id']) {
                http_response_code(403);
                echo 'Você não é o revisor desta etapa';
                return;
            }
        }

        $mrRepo->decide($fileId, 1, $decision, $notes);
        $opId = $this->findOperationIdByFile($fileId);
        if (!$opId) {
            http_response_code(500);
            echo 'Operação não encontrada para o arquivo';
            return;
        }
        $op = $opRepo->find($opId);

        if ($decision === 'rejected') {
            // Atualiza operação para "rejected" e notifica usuário pré-determinado
            $pdo = \Core\Database::pdo();
            $pdo->prepare('UPDATE operations SET status = "rejected" WHERE id = :id')->execute([':id' => $opId]);
            $ohRepo->log($opId, 'status_changed', 'Medição reprovada na primeira validação. Observações: ' . $notes);

            $rejId = (int)($op['rejection_notify_user_id'] ?? 0);
            if ($rejId) {
                $u = $userRepo->findBasic($rejId);
                if ($u) {
                    $subject = 'Medição reprovada — Operação #' . $opId;
                    $html = '<p>A medição da operação <strong>#' . $opId . '</strong> (' . htmlspecialchars($op['title']) . ') foi <strong>reprovada</strong> na 1ª validação.</p>'
                        . '<p><strong>Observações:</strong><br>' . nl2br(htmlspecialchars($notes)) . '</p>';
                    try {
                        \Core\Mailer::send($u['email'], $u['name'], $subject, $html);
                    } catch (\Throwable) {
                    }
                }
            }

            header('Location: /operations/' . $opId);
            exit;
        }

        // Aprovada na etapa 1: apenas registra; a fase 4 cuidará da próxima etapa
        $ohRepo->log($opId, 'measurement', 'Medição aprovada na 1ª validação. Observações: ' . $notes);
        header('Location: /operations/' . $opId);
    }
}
