<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\Controller;
use Core\Mailer;
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

        // status -> pending
        $pdo = \Core\Database::pdo();
        $pdo->prepare('UPDATE operations SET status = "pending" WHERE id = :id')->execute([':id' => $opId]);
        (new OperationHistoryRepository())->log($opId, 'status_changed', 'Arquivo de medição adicionado: operação marcada como pending.');

        // criar etapa 1 (revisor = responsável), se houver
        $responsibleId = (int)($op['responsible_user_id'] ?? 0);
        if ($responsibleId > 0) {
            (new MeasurementReviewRepository())->createStage($fileId, 1, $responsibleId);
        }

        // notificar responsável com link para analisar (1ª etapa)
        if ($responsibleId > 0) {
            $u = (new UserRepository())->findBasic($responsibleId);
            if ($u) {
                $base = rtrim($_ENV['APP_URL'] ?? '', '/');
                $link = $base . '/measurements/' . $fileId . '/review/1';
                $subject = 'Nova medição para a operação #' . $opId;
                $html = '<p>Olá, ' . htmlspecialchars($u['name']) . '</p>'
                    . '<p>Um novo arquivo de medição foi adicionado à operação <strong>#' . $opId . '</strong> (' . htmlspecialchars($op['title']) . ').</p>'
                    . '<p>Status da operação: <strong>Pendente</strong>.</p>'
                    . '<p><a href="' . htmlspecialchars($link) . '">Clique aqui para analisar</a>.</p>';
                try {
                    Mailer::send($u['email'], $u['name'], $subject, $html);
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

    /** GET: Mostra formulário de análise (estágio N) */
    public function reviewForm(int $fileId, int $stage = 1): void
    {
        $pdo = \Core\Database::pdo();

        // arquivo
        $st = $pdo->prepare('SELECT * FROM measurement_files WHERE id = :id LIMIT 1');
        $st->execute([':id' => $fileId]);
        $file = $st->fetch();
        if (!$file) {
            http_response_code(404);
            echo 'Arquivo não encontrado';
            return;
        }

        $mrRepo = new MeasurementReviewRepository();
        $mr = $mrRepo->getStage($fileId, $stage);

        // Fallback: cria a etapa se estiver faltando
        if (!$mr) {
            $opSt = $pdo->prepare('SELECT * FROM operations WHERE id = :op');
            $opSt->execute([':op' => (int)$file['operation_id']]);
            $op = $opSt->fetch();

            $reviewerId = null;
            if ($stage === 1) {
                $reviewerId = (int)($op['responsible_user_id'] ?? 0);
            } elseif ($stage === 2) {
                $reviewerId = (int)($op['stage2_reviewer_user_id'] ?? 0);
            }

            // fallback: primeiro usuário ativo
            if (!$reviewerId) {
                $reviewerId = (int)($pdo->query('SELECT id FROM users WHERE active = 1 ORDER BY id ASC LIMIT 1')->fetchColumn() ?: 0);
            }

            if ($reviewerId) {
                $mrRepo->createStage($fileId, $stage, $reviewerId);
                $mr = $mrRepo->getStage($fileId, $stage);
            } else {
                http_response_code(400);
                echo 'Etapa não encontrada: defina o revisor da etapa na operação.';
                return;
            }
        }

        // revisões anteriores
        $prev = $mrRepo->listByFile($fileId);

        $this->view('measurements/review', [
            'file'        => $file,
            'review'      => $mr,
            'operationId' => (int)$file['operation_id'],
            'stage'       => $stage,
            'previous'    => $prev,
        ]);
    }

    /** POST: Recebe decisão (Aprovar/Reprovar) com observações */
    public function reviewSubmit(int $fileId, int $stage = 1): void
    {
        $decision = ($_POST['decision'] ?? '') === 'approve' ? 'approved' : 'rejected';
        $notes    = trim((string)($_POST['notes'] ?? ''));

        $mrRepo   = new MeasurementReviewRepository();
        $opRepo   = new OperationRepository();
        $ohRepo   = new OperationHistoryRepository();
        $userRepo = new UserRepository();
        $pdo      = \Core\Database::pdo();

        // Se a etapa não existir (ex.: upload antigo), cria aqui também
        $stageRow = $mrRepo->getStage($fileId, $stage);
        if (!$stageRow) {
            $opIdTmp = (int)$pdo->query('SELECT operation_id FROM measurement_files WHERE id=' . (int)$fileId)->fetchColumn();
            if ($opIdTmp) {
                $opTmp = $opRepo->find($opIdTmp);
                $reviewerId = null;
                if ($stage === 1) {
                    $reviewerId = (int)($opTmp['responsible_user_id'] ?? 0);
                } elseif ($stage === 2) {
                    $reviewerId = (int)($opTmp['stage2_reviewer_user_id'] ?? 0);
                }
                if (!$reviewerId) {
                    $reviewerId = (int)($pdo->query('SELECT id FROM users WHERE active = 1 ORDER BY id ASC LIMIT 1')->fetchColumn() ?: 0);
                }
                if ($reviewerId) {
                    $mrRepo->createStage($fileId, $stage, $reviewerId);
                    $stageRow = $mrRepo->getStage($fileId, $stage);
                }
            }
        }

        if (!$stageRow) {
            http_response_code(400);
            echo 'Etapa não encontrada';
            return;
        }

        $mrRepo->decide($fileId, $stage, $decision, $notes);

        // descobre a operação
        $opId = (int)$pdo->query('SELECT operation_id FROM measurement_files WHERE id=' . (int)$fileId)->fetchColumn();
        if (!$opId) {
            http_response_code(500);
            echo 'Operação não encontrada';
            return;
        }
        $op = $opRepo->find($opId);

        if ($decision === 'rejected') {
            // Recusa: seta status e notifica destinatário pré-definido
            $pdo->prepare('UPDATE operations SET status = "rejected" WHERE id = :id')->execute([':id' => $opId]);
            $ohRepo->log($opId, 'status_changed', "Medição reprovada na {$stage}ª validação. Observações: " . $notes);

            $rejId = (int)($op['rejection_notify_user_id'] ?? 0);
            if ($rejId) {
                $u = $userRepo->findBasic($rejId);
                if ($u) {
                    $subject = 'Medição reprovada — Operação #' . $opId;
                    $html = '<p>A medição da operação <strong>#' . $opId . '</strong> (' . htmlspecialchars($op['title'])
                        . ') foi <strong>reprovada</strong> na ' . $stage . 'ª validação.</p>'
                        . '<p><strong>Observações:</strong><br>' . nl2br(htmlspecialchars($notes)) . '</p>';
                    try {
                        Mailer::send($u['email'], $u['name'], $subject, $html);
                    } catch (\Throwable) {
                    }
                }
            }

            header('Location: /operations/' . $opId);
            exit;
        }

        // Aprovado neste estágio
        if ($stage === 1) {
            // Cria etapa 2 e notifica o segundo revisor
            $stage2UserId = (int)($op['stage2_reviewer_user_id'] ?? 0);
            if ($stage2UserId) {
                if (!$mrRepo->getStage($fileId, 2)) {
                    $mrRepo->createStage($fileId, 2, $stage2UserId);
                }
                if ($u = $userRepo->findBasic($stage2UserId)) {
                    $base = rtrim($_ENV['APP_URL'] ?? '', '/');
                    $link = $base . '/measurements/' . $fileId . '/review/2';
                    $subject = '2ª validação — nova medição (Operação #' . $opId . ')';
                    $html = '<p>Olá, ' . htmlspecialchars($u['name']) . '</p>'
                        . '<p>Há uma nova medição para análise na <strong>2ª validação</strong> da operação '
                        . '<strong>#' . $opId . '</strong> (' . htmlspecialchars($op['title']) . ').</p>'
                        . '<p><a href="' . htmlspecialchars($link) . '">Clique aqui para analisar</a>.</p>';
                    try {
                        Mailer::send($u['email'], $u['name'], $subject, $html);
                    } catch (\Throwable) {
                    }
                }
            }
            $ohRepo->log($opId, 'measurement', 'Medição aprovada na 1ª validação. Observações: ' . $notes);
        } elseif ($stage === 2) {
            // Aprovada na 2ª validação (etapas seguintes serão tratadas depois)
            $ohRepo->log($opId, 'measurement', 'Medição aprovada na 2ª validação. Observações: ' . $notes);
        }

        header('Location: /operations/' . $opId);
        exit;
    }
}
