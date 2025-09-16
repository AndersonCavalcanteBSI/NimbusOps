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
                $subject = $this->mailSubject($op, 'Nova medição — 1ª Validação: Engenharia');
                $html = '<p>Olá, ' . htmlspecialchars($u['name']) . '</p>'
                    . '<p>Um novo arquivo de medição foi adicionado à operação '
                    . '<strong>#' . $opId . ($op['code'] ? ' (' . $this->esc($op['code']) . ')' : '') . '</strong> '
                    . '(' . htmlspecialchars((string)$op['title']) . ').</p>'
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
            } elseif ($stage === 3) { // 3ª validação
                $reviewerId = (int)($op['stage3_reviewer_user_id'] ?? 0);
            } elseif ($stage === 4) { // 4ª validação (gestão de pagamentos)
                $reviewerId = (int)($op['payment_manager_user_id'] ?? 0);
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

        // Pagamentos (somente na 4ª etapa)
        $payments = [];
        if ($stage === 4 && class_exists(\App\Repositories\MeasurementPaymentRepository::class)) {
            $pRepo = new \App\Repositories\MeasurementPaymentRepository();
            $payments = $pRepo->listByMeasurement($fileId);
        }

        $this->view('measurements/review', [
            'file'        => $file,
            'review'      => $mr,
            'operationId' => (int)$file['operation_id'],
            'stage'       => $stage,
            'previous'    => $prev,
            'payments'    => $payments, // para a etapa 4
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
                } elseif ($stage === 3) {
                    $reviewerId = (int)($opTmp['stage3_reviewer_user_id'] ?? 0);
                } elseif ($stage === 4) {
                    $reviewerId = (int)($opTmp['payment_manager_user_id'] ?? 0);
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
                    $base = rtrim($_ENV['APP_URL'] ?? '', '/');
                    $subject = $this->mailSubject($op, 'Medição reprovada');
                    $html = '<p>A medição da operação '
                        . '<strong>#' . $opId . ($op['code'] ? ' (' . $this->esc($op['code']) . ')' : '') . '</strong> '
                        . '(' . $this->esc((string)$op['title']) . ') foi <strong>reprovada</strong> na '
                        . $stage . 'ª validação.</p>'
                        . '<p><strong>Observações:</strong><br>' . nl2br($this->esc($notes)) . '</p>';
                    try {
                        Mailer::send($u['email'], $u['name'], $subject, $html);
                    } catch (\Throwable) {
                    }
                }
            }

            header('Location: /operations/' . $opId);
            exit;
        }

        // ===== Aprovado neste estágio =====
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
                    $subject = $this->mailSubject($op, '2ª validação — nova medição');
                    $html = '<p>Olá, ' . $this->esc($u['name']) . '</p>'
                        . '<p>Há uma nova medição para análise na <strong>2ª validação: Gestão</strong> da operação '
                        . '<strong>#' . $opId . ($op['code'] ? ' (' . $this->esc($op['code']) . ')' : '') . '</strong> '
                        . '(' . $this->esc((string)$op['title']) . ').</p>'
                        . '<p><a href="' . $this->esc($link) . '">Clique aqui para analisar</a>.</p>';
                    try {
                        Mailer::send($u['email'], $u['name'], $subject, $html);
                    } catch (\Throwable) {
                    }
                }
            }
            $ohRepo->log($opId, 'measurement', 'Medição aprovada na 1ª validação. Observações: ' . $notes);
        } elseif ($stage === 2) {
            // Cria etapa 3 e notifica o 3º revisor
            $stage3UserId = (int)($op['stage3_reviewer_user_id'] ?? 0);
            if ($stage3UserId) {
                if (!$mrRepo->getStage($fileId, 3)) {
                    $mrRepo->createStage($fileId, 3, $stage3UserId);
                }
                if ($u = $userRepo->findBasic($stage3UserId)) {
                    $base = rtrim($_ENV['APP_URL'] ?? '', '/');
                    $link = $base . '/measurements/' . $fileId . '/review/3';
                    $subject = $this->mailSubject($op, '3ª validação — nova medição');
                    $html = '<p>Olá, ' . $this->esc($u['name']) . '</p>'
                        . '<p>Há uma nova medição para análise na <strong>3ª validação: Jurídico</strong> da operação '
                        . '<strong>#' . $opId . ($op['code'] ? ' (' . $this->esc($op['code']) . ')' : '') . '</strong> '
                        . '(' . $this->esc((string)$op['title']) . ').</p>'
                        . '<p><a href="' . $this->esc($link) . '">Clique aqui para analisar</a>.</p>';
                    try {
                        Mailer::send($u['email'], $u['name'], $subject, $html);
                    } catch (\Throwable) {
                    }
                }
            }
            $ohRepo->log($opId, 'measurement', 'Medição aprovada na 2ª validação. Observações: ' . $notes);
        } elseif ($stage === 3) {
            // Etapa 3 aprovada: cria etapa 4 (gestão de pagamentos) e notifica para /review/4
            $ohRepo->log($opId, 'measurement', 'Medição aprovada na 3ª validação. Observações: ' . $notes);

            $pmId = (int)($op['payment_manager_user_id'] ?? 0);
            if ($pmId) {
                if (!$mrRepo->getStage($fileId, 4)) {
                    $mrRepo->createStage($fileId, 4, $pmId);
                }
                if ($u = $userRepo->findBasic($pmId)) {
                    $base = rtrim($_ENV['APP_URL'] ?? '', '/');
                    $link = $base . '/measurements/' . $fileId . '/review/4';
                    $subject = $this->mailSubject($op, 'Medição aprovada — registrar pagamentos (4ª etapa)');
                    $html = '<p>Olá, ' . $this->esc($u['name']) . '</p>'
                        . '<p>A medição da operação <strong>#' . $opId
                        . ($op['code'] ? ' (' . $this->esc($op['code']) . ')' : '')
                        . '</strong> (' . $this->esc((string)$op['title'])
                        . ') foi aprovada nas três validações.</p>'
                        . '<p>Acesse a <strong>4ª etapa</strong> para registrar/verificar pagamentos: '
                        . '<a href="' . $this->esc($link) . '">Abrir 4ª validação</a>.</p>';
                    try {
                        Mailer::send($u['email'], $u['name'], $subject, $html);
                    } catch (\Throwable) {
                    }
                }
            }
        } elseif ($stage === 4) {
            // 4ª etapa (gestão de pagamentos)
            $hasPayments = false;
            if (class_exists(\App\Repositories\MeasurementPaymentRepository::class)) {
                $pRepo = new \App\Repositories\MeasurementPaymentRepository();
                $hasPayments = count($pRepo->listByMeasurement($fileId)) > 0;
            }

            if ($decision === 'approved') {
                if (!$hasPayments) {
                    http_response_code(400);
                    echo 'Cadastre ao menos um pagamento antes de aprovar a 4ª etapa.';
                    return;
                }
                $ohRepo->log($opId, 'payment_checked', '4ª etapa aprovada: pagamentos registrados/verificados. Observações: ' . $notes);
            }
            // Se "rejected", o bloco de recusa acima já cuidou (status rejected + e-mail)
        }

        header('Location: /operations/' . $opId);
        exit;
    }

    /** GET: Formulário para registrar pagamentos da medição */
    public function paymentsForm(int $fileId): void
    {
        $pdo = \Core\Database::pdo();

        // arquivo + operação
        $st = $pdo->prepare(
            'SELECT mf.*, o.title AS op_title, o.id AS op_id
               FROM measurement_files mf
               JOIN operations o ON o.id = mf.operation_id
              WHERE mf.id = :id'
        );
        $st->execute([':id' => $fileId]);
        $file = $st->fetch();
        if (!$file) {
            http_response_code(404);
            echo 'Arquivo não encontrado';
            return;
        }

        // pagamentos já registrados (se houver)
        $pRepo = new \App\Repositories\MeasurementPaymentRepository();
        $payments = $pRepo->listByMeasurement($fileId);

        $this->view('measurements/payments_new', [
            'file' => $file,
            'payments' => $payments,
        ]);
    }

    public function paymentsStore(int $fileId): void
    {
        $pdo = \Core\Database::pdo();
        $opId = (int)$pdo->query('SELECT operation_id FROM measurement_files WHERE id=' . (int)$fileId)->fetchColumn();
        if (!$opId) {
            http_response_code(404);
            echo 'Medição/Operação não encontrada';
            return;
        }

        // Coleta linhas do formulário
        $payDates = $_POST['pay_date'] ?? [];
        $amounts  = $_POST['amount'] ?? [];
        $methods  = $_POST['method'] ?? [];
        $notesArr = $_POST['notes'] ?? [];

        $rows = [];
        $n = max(count((array)$payDates), count((array)$amounts));
        for ($i = 0; $i < $n; $i++) {
            $dt = trim((string)($payDates[$i] ?? ''));
            $am = (float)($amounts[$i] ?? 0);
            if ($dt === '' || $am <= 0) {
                continue;
            }
            $rows[] = [
                'pay_date' => $dt,
                'amount'   => $am,
                'method'   => trim((string)($methods[$i] ?? '')),
                'notes'    => trim((string)($notesArr[$i] ?? '')),
            ];
        }

        if (!$rows) {
            http_response_code(400);
            echo 'Informe ao menos um pagamento válido.';
            return;
        }

        // (sem autenticação) created_by = null
        $pRepo = new \App\Repositories\MeasurementPaymentRepository();
        $inserted = $pRepo->createMany($opId, $fileId, $rows, null);

        // registra no histórico da operação
        $oh = new \App\Repositories\OperationHistoryRepository();
        $oh->log($opId, 'payment_recorded', 'Pagamentos registrados para a medição #' . $fileId . ' (itens: ' . $inserted . ').');

        // ===== FASE 7: notifica o "finalizador" com histórico completo + link para finalizar =====
        $opRepo   = new \App\Repositories\OperationRepository();
        $userRepo = new \App\Repositories\UserRepository();
        $op       = $opRepo->find($opId);

        $finalizerId = (int)($op['payment_finalizer_user_id'] ?? 0);
        if ($finalizerId) {
            if ($u = $userRepo->findBasic($finalizerId)) {
                $base = rtrim($_ENV['APP_URL'] ?? '', '/');
                $link = $base . '/measurements/' . $fileId . '/finalize';

                $subject = $this->mailSubject($op, 'Finalizar pagamento — ação necessária');
                $html    = $this->buildMeasurementSummaryHtml($opId, $fileId);
                $html   .= '<p style="margin-top:12px"><a href="' . htmlspecialchars($link) . '">Confirmar finalização do pagamento</a></p>';

                try {
                    Mailer::send($u['email'], $u['name'], $subject, $html);
                } catch (\Throwable) {
                }
            }
        }

        // redirect com mensagem de orientação
        header('Location: /measurements/' . $fileId . '/payments/new?ok=1');
        exit;
    }

    /** Monta HTML com histórico completo da medição (revisões + pagamentos) */
    private function buildMeasurementSummaryHtml(int $opId, int $fileId): string
    {
        $pdo    = \Core\Database::pdo();
        $opRepo = new \App\Repositories\OperationRepository();
        $mrRepo = new \App\Repositories\MeasurementReviewRepository();
        $pRepo  = class_exists(\App\Repositories\MeasurementPaymentRepository::class)
            ? new \App\Repositories\MeasurementPaymentRepository()
            : null;

        $op = $opRepo->find($opId) ?? [];
        $st = $pdo->prepare('SELECT * FROM measurement_files WHERE id = :id');
        $st->execute([':id' => $fileId]);
        $file = $st->fetch() ?: [];

        $reviews  = $mrRepo->listByFile($fileId);
        $payments = $pRepo ? $pRepo->listByMeasurement($fileId) : [];
        $total    = 0.0;
        foreach ($payments as $p) {
            $total += (float)$p['amount'];
        }

        ob_start(); ?>
        <div>
            <h3 style="margin:0 0 8px">Resumo da Medição</h3>
            <p><strong>Operação #<?= (int)$opId ?><?= !empty($op['code']) ? ' (' . $this->esc((string)$op['code']) . ')' : '' ?></strong> — <?= htmlspecialchars((string)($op['title'] ?? '')) ?></p>
            <p><strong>Arquivo:</strong> <?= htmlspecialchars((string)($file['filename'] ?? '')) ?></p>

            <?php if ($reviews): ?>
                <h4 style="margin:16px 0 6px">Histórico de Análises</h4>
                <table border="1" cellpadding="6" cellspacing="0" width="100%" style="border-collapse:collapse">
                    <thead>
                        <tr>
                            <th>Etapa</th>
                            <th>Status</th>
                            <th>Revisor</th>
                            <th>Quando</th>
                            <th>Observações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reviews as $r): ?>
                            <tr>
                                <td><?= (int)$r['stage'] ?>ª</td>
                                <td><?= htmlspecialchars((string)$r['status']) ?></td>
                                <td><?= htmlspecialchars((string)($r['reviewer_name'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string)($r['reviewed_at'] ?? '')) ?></td>
                                <td><?= nl2br(htmlspecialchars((string)($r['notes'] ?? ''))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php if ($payments): ?>
                <h4 style="margin:16px 0 6px">Pagamentos Registrados</h4>
                <table border="1" cellpadding="6" cellspacing="0" width="100%" style="border-collapse:collapse">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Método</th>
                            <th>Observações</th>
                            <th style="text-align:right">Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $p): ?>
                            <tr>
                                <td><?= htmlspecialchars((string)$p['pay_date']) ?></td>
                                <td><?= htmlspecialchars((string)($p['method'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string)($p['notes']  ?? '-')) ?></td>
                                <td style="text-align:right">R$ <?= number_format((float)$p['amount'], 2, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr>
                            <td colspan="3" style="text-align:right"><strong>Total</strong></td>
                            <td style="text-align:right"><strong>R$ <?= number_format((float)$total, 2, ',', '.') ?></strong></td>
                        </tr>
                    </tbody>
                </table>
            <?php else: ?>
                <p><em>Não há pagamentos registrados ainda.</em></p>
            <?php endif; ?>
        </div>
<?php
        return (string)ob_get_clean();
    }

    /** GET: Mostra histórico completo e botão para finalizar */
    public function finalizeForm(int $fileId): void
    {
        $pdo = \Core\Database::pdo();

        // arquivo + operação
        $st = $pdo->prepare('SELECT mf.*, o.id AS op_id, o.title AS op_title
                           FROM measurement_files mf
                           JOIN operations o ON o.id = mf.operation_id
                          WHERE mf.id = :id');
        $st->execute([':id' => $fileId]);
        $file = $st->fetch();
        if (!$file) {
            http_response_code(404);
            echo 'Medição não encontrada';
            return;
        }

        $opId = (int)$file['op_id'];

        $mrRepo  = new \App\Repositories\MeasurementReviewRepository();
        $reviews = $mrRepo->listByFile($fileId);

        $payments = [];
        if (class_exists(\App\Repositories\MeasurementPaymentRepository::class)) {
            $pRepo   = new \App\Repositories\MeasurementPaymentRepository();
            $payments = $pRepo->listByMeasurement($fileId);
        }

        $this->view('measurements/finalize', [
            'file'        => $file,
            'operationId' => $opId,
            'reviews'     => $reviews,
            'payments'    => $payments,
        ]);
    }

    /** POST: Confirma finalização => status = completed */
    public function finalizeSubmit(int $fileId): void
    {
        $pdo = \Core\Database::pdo();

        $opId = (int)$pdo->query('SELECT operation_id FROM measurement_files WHERE id=' . (int)$fileId)->fetchColumn();
        if (!$opId) {
            http_response_code(404);
            echo 'Operação não encontrada';
            return;
        }

        // Atualiza status para "completed"
        $pdo->prepare('UPDATE operations SET status = "completed" WHERE id = :id')->execute([':id' => $opId]);

        // Log
        $oh = new \App\Repositories\OperationHistoryRepository();
        $oh->log($opId, 'status_changed', 'Pagamento finalizado: operação marcada como "completed".');

        header('Location: /operations/' . $opId);
        exit;
    }

    /** Helper: escapa rapidamente (atalho) */
    private function esc(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Helper: padroniza assuntos com código da operação quando existir */
    private function mailSubject(array $op, string $base): string
    {
        $code = trim((string)($op['code'] ?? ''));
        return $code !== '' ? '[' . $code . '] ' . $base : $base;
    }
}
