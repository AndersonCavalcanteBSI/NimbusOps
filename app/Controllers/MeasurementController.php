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
use App\Repositories\OperationNotifyRepository;

final class MeasurementController extends Controller
{
    // -------------------------
    // Status do pipeline
    // -------------------------
    private const ST_ENGENHARIA = 'Engenharia';
    private const ST_GESTAO     = 'Gestão';
    private const ST_JURIDICO   = 'Compliance';
    private const ST_PAGAMENTO  = 'Pagamento';
    private const ST_FINALIZAR  = 'Finalizar';
    private const ST_COMPLETO   = 'Completo';
    private const ST_REJEITADO  = 'Rejeitado';
    /** Usuário logado (usa DEV_USER_ID apenas em ambiente de desenvolvimento) */
    private function currentUserId(): ?int
    {
        if (isset($_SESSION['user_id'])) {
            return (int) $_SESSION['user_id'];
        }
        $appDebug = strtolower((string)($_ENV['APP_DEBUG'] ?? 'false')) === 'true';
        $appEnv   = strtolower((string)($_ENV['APP_ENV']   ?? '')) === 'local';
        if ($appDebug || $appEnv) {
            $dev = (int) ($_ENV['DEV_USER_ID'] ?? 0);
            return $dev > 0 ? $dev : null;
        }
        return null;
    }
    /** Status requerido para cada etapa */
    private function requiredStatusForStage(int $stage): ?string
    {
        return match ($stage) {
            1 => self::ST_ENGENHARIA,
            2 => self::ST_GESTAO,
            3 => self::ST_JURIDICO,
            4 => self::ST_PAGAMENTO,
            default => null,
        };
    }
    /** Normaliza texto de status: trim, lowercase e remove acentos */
    private function normalizeStatus(string $s): string
    {
        $s = trim($s);
        if (class_exists(\Transliterator::class)) {
            $tr = \Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC');
            if ($tr) {
                $s = $tr->transliterate($s);
            }
        } else {
            $from = ['á', 'à', 'â', 'ã', 'ä', 'é', 'è', 'ê', 'í', 'ì', 'î', 'ï', 'ó', 'ò', 'ô', 'õ', 'ö', 'ú', 'ù', 'û', 'ü', 'ç', 'Á', 'À', 'Â', 'Ã', 'Ä', 'É', 'È', 'Ê', 'Í', 'Ì', 'Î', 'Ï', 'Ó', 'Ò', 'Ô', 'Õ', 'Ö', 'Ú', 'Ù', 'Û', 'Ü', 'Ç'];
            $to   = ['a', 'a', 'a', 'a', 'a', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'c', 'A', 'A', 'A', 'A', 'A', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'C'];
            $s = str_replace($from, $to, $s);
        }
        return mb_strtolower($s, 'UTF-8');
    }
    /** Compara dois status de forma tolerante (case/acentos/espaços) */
    private function statusEquals(?string $a, ?string $b): bool
    {
        if ($a === null || $b === null) return false;
        return $this->normalizeStatus($a) === $this->normalizeStatus($b);
    }
    /** Lê o id do revisor aceitando reviewer_user_id ou reviewer_id */
    private function reviewerIdFrom(array $row): int
    {
        return (int)($row['reviewer_user_id'] ?? $row['reviewer_id'] ?? 0);
    }
    /** Verifica se o usuário pode revisar esta etapa */
    private function userCanReview(int $uid, int $expectedReviewerId): bool
    {
        $userRepo = new UserRepository();
        $user = $userRepo->findBasic($uid);
        if (!$user) {
            return false;
        }
        $isAdmin = ($user['role'] ?? '') === 'admin';
        return $uid === $expectedReviewerId || $isAdmin;
    }
    /** Atualiza status da operação e registra no histórico. */
    private function setStatus(int $opId, string $status, string $note = ''): void
    {
        $pdo = \Core\Database::pdo();
        $pdo->prepare('UPDATE operations SET status = :s WHERE id = :id')->execute([
            ':s'  => $status,
            ':id' => $opId,
        ]);
        (new OperationHistoryRepository())->log(
            $opId,
            'status_changed',
            ($note !== '' ? $note . ' ' : '') . 'Status: ' . $status . '.'
        );
    }
    /** ===== NOVO: acrescenta observação (sem sobrescrever) e grava a decisão ===== */
    private function appendDecisionNotesAndSetStatus(
        int $fileId,
        int $stage,
        string $newStatus,     // 'approved' | 'rejected' | 'pending'
        string $notes,
        ?int $uid
    ): void {
        $pdo = \Core\Database::pdo();
        // bloco a acrescentar (só se houver texto novo)
        $decor = '';
        $notes = trim($notes);
        if ($notes !== '') {
            $by  = 'NimbusOps';
            if ($uid) {
                $u = (new \App\Repositories\UserRepository())->findBasic($uid);
                if ($u && !empty($u['name'])) {
                    $by = $u['name'];
                }
            }
            $decor = "\n\n--- " . date('d/m/Y H:i:s') . " por " . $by . " ---\n" . $notes;
        }
        if ($decor === '') {
            // Sem novas notas: não mexe no campo notes
            $sql = 'UPDATE measurement_reviews SET status = :st, reviewed_at = NOW() WHERE measurement_file_id = :f AND stage = :s';
            $pdo->prepare($sql)->execute([
                ':st' => $newStatus,
                ':f'  => $fileId,
                ':s'  => $stage,
            ]);
        } else {
            // Com novas notas: concatena ao notes existente
            $sql = 'UPDATE measurement_reviews SET status = :st, reviewed_at = NOW(), notes = CONCAT(COALESCE(notes, ""), :decor) WHERE measurement_file_id = :f AND stage = :s';
            $pdo->prepare($sql)->execute([
                ':st'    => $newStatus,
                ':decor' => $decor,
                ':f'     => $fileId,
                ':s'     => $stage,
            ]);
        }
    }
    private function ensureStagePending(int $fileId, int $stage, ?int $reviewerId = null): void
    {
        $pdo    = \Core\Database::pdo();
        $mrRepo = new MeasurementReviewRepository();
        $row = $mrRepo->getStage($fileId, $stage);
        if (!$row) {
            // cria se não existir (revisor obrigatório)
            if ($reviewerId) {
                $mrRepo->createStage($fileId, $stage, $reviewerId);
                return;
            }
            return; // sem revisor definido, não criamos aqui
        }
        // se existir mas não estiver pendente, reabre
        if (($row['status'] ?? '') !== 'pending') {
            $pdo->prepare('UPDATE measurement_reviews SET status = "pending", reviewed_at = NULL WHERE measurement_file_id = :f AND stage = :s')->execute([':f' => $fileId, ':s' => $stage]);
        }
    }
    public function create(): void
    {
        $ops = (new OperationRepository())->paginate([], 1, 200, 'title', 'asc')['data'] ?? [];
        $this->view('measurements/upload', ['operations' => $ops]);
    }
    public function store(): void
    {
        $opId = (int)($_POST['operation_id'] ?? 0);
        /*if ($opId <= 0) {
            http_response_code(400);
            echo 'Operação inválida';
            return;
        }*/
        if ($opId <= 0) {
            $_SESSION['flash_error'] = 'Selecione uma operação antes de enviar o arquivo.';
            session_write_close();
            header('Location: /measurements/upload');
            exit;
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
        // Status inicial: Engenharia (nova medição adicionada)
        $this->setStatus($opId, self::ST_ENGENHARIA, 'Arquivo de medição adicionado.');
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
                // Assunto com o NOME (título) da operação
                $subject = $this->esc((string)$op['title']) . ': ' . 'Nova medição — 1ª Validação (Engenharia)';
                // Corpo destacando o NOME; código vira opcional entre parênteses
                $html = '<p>Olá, ' . htmlspecialchars($u['name']) . '</p>'
                    . '<p>Um novo arquivo de medição foi adicionado à operação '
                    . '<strong>' . $this->esc((string)$op['title']) . '</strong>'
                    . (!empty($op['code']) ? ' (Código: ' . $this->esc((string)$op['code']) . ')' : '')
                    . '.</p>'
                    . '<p>Status da operação: <strong>' . self::ST_ENGENHARIA . '</strong>.</p>'
                    . // CTA estilizado (compatível com a maioria dos clientes de e-mail)
                    $cta = '
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:16px 0">
                            <tr>
                                <td align="left" style="border-radius:8px; background:#0B2A4A;">
                                    <a href="' . $this->esc($link) . '"style="display:inline-block; padding:12px 18px; border-radius:8px; background:#0B2A4A; border:1px solid #0B2A4A; font-family:Arial,Helvetica,sans-serif; font-size:14px; line-height:20px; text-decoration:none; color:#ffffff; font-weight:600;">
                                        Analisar medição
                                    </a>
                                </td>
                            </tr>
                        </table>';
                $this->smtpSend($u['email'], $u['name'], $subject, $html, $opId);
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
        // Se a medição já estiver concluída, vá para o histórico
        if (isset($file['status']) && mb_strtolower((string)$file['status'], 'UTF-8') === mb_strtolower('Concluído', 'UTF-8')) {
            header('Location: /measurements/' . (int)$file['id'] . '/history');
            exit;
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
            } elseif ($stage === 3) {
                $reviewerId = (int)($op['stage3_reviewer_user_id'] ?? 0);
            } elseif ($stage === 4) {
                $reviewerId = (int)($op['payment_manager_user_id'] ?? 0);
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
        // ==== PERMISSÃO DE QUEM PODE ANALISAR ====
        $opId   = (int)$file['operation_id'];
        $opRepo = new OperationRepository();
        $op     = $opRepo->find($opId);
        $uid        = $this->currentUserId();
        $requiredSt = $this->requiredStatusForStage($stage);
        // leitura-apenas quando operação = Completo OU arquivo = Concluído
        $readOnly = $this->statusEquals((string)($op['status'] ?? ''), self::ST_COMPLETO)
            || (isset($file['status']) && mb_strtolower((string)$file['status'], 'UTF-8') === mb_strtolower('Concluído', 'UTF-8'));
        // highlight-start
        // ===== ALTERAÇÃO DE SEGURANÇA =====
        // Se não for apenas leitura, um usuário VÁLIDO é obrigatório.
        if (!$readOnly) {
            if (!$uid) {
                http_response_code(401); // 401 Unauthorized
                echo 'Acesso não autorizado. Faça login para visualizar esta página.';
                return;
            }
            // A partir daqui, o código pode assumir que $uid é um inteiro válido.
            if ($requiredSt && !$this->statusEquals((string)($op['status'] ?? ''), $requiredSt)) {
                http_response_code(403);
                echo 'Esta etapa não está disponível no status atual da operação.';
                return;
            }
            $expectedReviewerId = $this->reviewerIdFrom((array)$mr);
            if (!$this->userCanReview($uid, $expectedReviewerId)) {
                if (strtolower((string)($_ENV['APP_DEBUG'] ?? 'false')) === 'true') {
                    error_log(sprintf(
                        '[reviewForm] Bloqueado: uid=%s, expected=%s, stage=%d, file=%d',
                        var_export($uid, true),
                        var_export($expectedReviewerId, true),
                        (int)$stage,
                        (int)$fileId
                    ));
                    echo 'Você não tem permissão para revisar esta etapa. (debug uid='
                        . (int)$uid . ' expected=' . (int)$expectedReviewerId . ')';
                } else {
                    echo 'Você não tem permissão para revisar esta etapa.';
                }
                http_response_code(403);
                return;
            }
            if (isset($mr['status']) && $mr['status'] !== 'pending') {
                http_response_code(403);
                echo 'Esta etapa já foi analisada. Aguarde as próximas validações.';
                return;
            }
        }
        // highlight-end
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
            'payments'    => $payments,
            'readOnly'    => $readOnly,
        ]);
    }
    public function reviewSubmit(int $fileId, int $stage = 1): void
    {
        $decision = ($_POST['decision'] ?? '') === 'approve' ? 'approved' : 'rejected';
        $notes    = trim((string)($_POST['notes'] ?? ''));
        $mrRepo   = new MeasurementReviewRepository();
        $opRepo   = new OperationRepository();
        $ohRepo   = new OperationHistoryRepository();
        $userRepo = new UserRepository();
        $pdo      = \Core\Database::pdo();
        // Se a etapa não existir, cria
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
        // ==== PERMISSÕES ====
        $uid        = $this->currentUserId();
        $opIdCheck  = (int)$pdo->query('SELECT operation_id FROM measurement_files WHERE id=' . (int)$fileId)->fetchColumn();
        $opCheck    = (new OperationRepository())->find($opIdCheck);
        $requiredSt = $this->requiredStatusForStage($stage);
        $fileStatus = (string)\Core\Database::pdo()->query('SELECT status FROM measurement_files WHERE id=' . (int)$fileId)->fetchColumn();
        if ($this->statusEquals((string)($opCheck['status'] ?? ''), self::ST_COMPLETO) || mb_strtolower($fileStatus, 'UTF-8') === mb_strtolower('Concluído', 'UTF-8')) {
            http_response_code(403);
            echo 'Medição finalizada — somente leitura.';
            return;
        }
        // highlight-start
        // ===== ALTERAÇÃO DE SEGURANÇA =====
        // Um usuário VÁLIDO é obrigatório para submeter uma decisão.
        if (!$uid) {
            http_response_code(401); // 401 Unauthorized
            echo 'Acesso não autorizado. Faça login para realizar esta ação.';
            return;
        }
        // A partir daqui, o código pode assumir que $uid é um inteiro válido.
        if ($requiredSt && !$this->statusEquals((string)($opCheck['status'] ?? ''), $requiredSt)) {
            http_response_code(403);
            echo 'Esta etapa não está disponível no status atual da operação.';
            return;
        }
        $expectedReviewerId = $this->reviewerIdFrom((array)$stageRow);
        if (!$this->userCanReview($uid, $expectedReviewerId)) {
            if (strtolower((string)($_ENV['APP_DEBUG'] ?? 'false')) === 'true') {
                error_log(sprintf(
                    '[reviewSubmit] Bloqueado: uid=%s, expected=%s, stage=%d, file=%d',
                    var_export($uid, true),
                    var_export($expectedReviewerId, true),
                    (int)$stage,
                    (int)$fileId
                ));
                echo 'Você não tem permissão para revisar esta etapa. (debug uid='
                    . (int)$uid . ' expected=' . (int)$expectedReviewerId . ')';
            } else {
                echo 'Você não tem permissão para revisar esta etapa.';
            }
            http_response_code(403);
            return;
        }
        if (isset($stageRow['status']) && $stageRow['status'] !== 'pending') {
            http_response_code(403);
            echo 'Esta etapa já foi analisada. Aguarde as próximas validações.';
            return;
        }
        // highlight-end
        $this->appendDecisionNotesAndSetStatus($fileId, $stage, $decision, $notes, $uid);
        // descobre a operação
        $opId = (int)$pdo->query('SELECT operation_id FROM measurement_files WHERE id=' . (int)$fileId)->fetchColumn();
        if (!$opId) {
            http_response_code(500);
            echo 'Operação não encontrada';
            return;
        }
        $op = $opRepo->find($opId);
        if ($decision === 'rejected') {
            if ($stage === 1) {
                // 1ª etapa: arquivo rejeitado e fechado
                $pdo->prepare('UPDATE measurement_files SET status = :s, closed_at = NOW() WHERE id = :id')->execute([':s' => 'Rejeitado', ':id' => $fileId]);
                // 1ª etapa: operação inteira rejeitada
                $newStatus = self::ST_REJEITADO;
                $note      = "Medição reprovada na 1ª validação (Engenharia).";
                $this->setStatus($opId, $newStatus, $note);
                $ohRepo->log($opId, 'measurement', $note . ' Observações: ' . $notes);
            } else {
                // Demais etapas: arquivo rejeitado mas não fechado (pode voltar)
                $pdo->prepare('UPDATE measurement_files SET status = :s WHERE id = :id')->execute([':s' => 'Rejeitado', ':id' => $fileId]);
                switch ($stage) {
                    case 2:
                        $newStatus = self::ST_ENGENHARIA;
                        $note      = "Medição reprovada na 2ª validação. Retorno para Engenharia.";
                        break;
                    case 3:
                        $newStatus = self::ST_GESTAO;
                        $note      = "Medição reprovada na 3ª validação. Retorno para Gestão.";
                        break;
                    case 4:
                        $newStatus = self::ST_JURIDICO;
                        $note      = "Medição reprovada na 4ª validação. Retorno para Compliance.";
                        break;
                    default:
                        $newStatus = self::ST_PAGAMENTO;
                        $note      = "Medição reprovada na {$stage}ª validação. Retorno para Financeiro/Pagamento.";
                        break;
                }
                // Atualiza status da operação e log
                $this->setStatus($opId, $newStatus, $note);
                $ohRepo->log($opId, 'measurement', $note . ' Observações: ' . $notes);
                // Reabre a etapa anterior
                $prevStage = $stage - 1;
                $pdo->prepare('UPDATE measurement_reviews SET status = "pending", reviewed_at = NULL WHERE measurement_file_id = :f AND stage = :s')->execute([':f' => $fileId, ':s' => $prevStage]);
            }
            $this->sendRejectionEmails($op, $opId, $fileId, $stage, $notes);
            header('Location: /operations/' . $opId);
            exit;
        }
        // ===== Aprovado neste estágio =====
        if ($stage === 1) {
            // Garante que a ETAPA 2 exista e esteja PENDENTE (mesmo que já tenha ficado "rejected" antes)
            $stage2UserId = (int)($op['stage2_reviewer_user_id'] ?? 0);
            if ($stage2UserId) {
                $this->ensureStagePending($fileId, 2, $stage2UserId);
                if ($u = $userRepo->findBasic($stage2UserId)) {
                    $base = rtrim($_ENV['APP_URL'] ?? '', '/');
                    $link = $base . '/measurements/' . (int)$fileId . '/review/2';
                    $opTitle = trim((string)($op['title'] ?? ''));
                    $subject = $opTitle . ' — ' . 'Nova medição para análise — 2ª validação (Gestão)';
                    $cta = '
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:16px 0">
                            <tr>
                                <td align="left" style="border-radius:8px; background:#0B2A4A;">
                                    <a href="' . $this->esc($link) . '"style="display:inline-block; padding:12px 18px; border-radius:8px; background:#0B2A4A; border:1px solid #0B2A4A; font-family:Arial,Helvetica,sans-serif; font-size:14px; line-height:20px; text-decoration:none; color:#ffffff; font-weight:600;">
                                        Analisar medição
                                    </a>
                                </td>
                            </tr>
                        </table>';
                    $html  = '<p>Olá, ' . $this->esc((string)$u['name']) . '.</p>'
                        . '<p>Há uma nova medição para análise na <strong>2ª validação: Gestão</strong> da operação '
                        . '<strong>' . $this->esc($opTitle) . '</strong>.</p>'
                        . $cta;
                    $this->smtpSend($u['email'], $u['name'], $subject, $html, (int)$opId);
                }
            }
            $this->setStatus($opId, self::ST_GESTAO, 'Aprovada na 1ª validação; próxima etapa: Gestão.');
            $ohRepo->log($opId, 'measurement', 'Medição aprovada na 1ª validação. Observações: ' . $notes);
        } elseif ($stage === 2) {
            // Garante ETAPA 3 pendente
            $stage3UserId = (int)($op['stage3_reviewer_user_id'] ?? 0);
            if ($stage3UserId) {
                $this->ensureStagePending($fileId, 3, $stage3UserId);
                if ($u = $userRepo->findBasic($stage3UserId)) {
                    $base = rtrim($_ENV['APP_URL'] ?? '', '/');
                    $link = $base . '/measurements/' . (int)$fileId . '/review/3';
                    $opTitle = trim((string)($op['title'] ?? ''));
                    $subject = $opTitle . ' — ' . 'Nova medição para análise — 3ª validação (Compliance)';
                    $cta = '
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:16px 0">
                            <tr>
                                <td align="left" style="border-radius:8px; background:#0B2A4A;">
                                    <a href="' . $this->esc($link) . '"style="display:inline-block; padding:12px 18px; border-radius:8px; background:#0B2A4A; border:1px solid #0B2A4A; font-family:Arial,Helvetica,sans-serif; font-size:14px; line-height:20px; text-decoration:none; color:#ffffff; font-weight:600;">
                                        Analisar medição
                                    </a>
                                </td>
                            </tr>
                        </table>';
                    $html  = '<p>Olá, ' . $this->esc((string)$u['name']) . '.</p>'
                        . '<p>Há uma nova medição para análise na <strong>3ª validação: Compliance</strong> da operação '
                        . '<strong>' . $this->esc($opTitle) . '</strong>.</p>'
                        . $cta;
                    $this->smtpSend($u['email'], $u['name'], $subject, $html, (int)$opId);
                }
            }
            $this->setStatus($opId, self::ST_JURIDICO, 'Aprovada na 2ª validação; próxima etapa: Compliance.');
            $ohRepo->log($opId, 'measurement', 'Medição aprovada na 2ª validação. Observações: ' . $notes);
        } elseif ($stage === 3) {
            // Garante ETAPA 4 pendente
            $pmId = (int)($op['payment_manager_user_id'] ?? 0);
            if ($pmId) {
                $this->ensureStagePending($fileId, 4, $pmId);
                if ($u = $userRepo->findBasic($pmId)) {
                    $base = rtrim($_ENV['APP_URL'] ?? '', '/');
                    $link = $base . '/measurements/' . (int)$fileId . '/review/4';
                    $opTitle = trim((string)($op['title'] ?? ''));
                    $subject = $opTitle . ': ' . 'Medição aprovada — registrar pagamentos';
                    $cta = '
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:16px 0">
                            <tr>
                                <td align="left" style="border-radius:8px; background:#0B2A4A;">
                                    <a href="' . $this->esc($link) . '"style="display:inline-block; padding:12px 18px; border-radius:8px; background:#0B2A4A; border:1px solid #0B2A4A; font-family:Arial,Helvetica,sans-serif; font-size:14px; line-height:20px; text-decoration:none; color:#ffffff; font-weight:600;">
                                        Abrir Pagamentos
                                    </a>
                                </td>
                            </tr>
                        </table>';
                    $html  = '<p>Olá, ' . $this->esc((string)$u['name']) . '.</p>'
                        . '<p>A medição da operação <strong>' . $this->esc($opTitle) . '</strong> '
                        . 'foi aprovada em todas as três validações.</p>'
                        . '<p>Agora, acesse a <strong>4ª etapa</strong> para registrar ou conferir os pagamentos.</p>'
                        . $cta;
                    $this->smtpSend($u['email'], $u['name'], $subject, $html, (int)$opId);
                }
            }
            $this->setStatus($opId, self::ST_PAGAMENTO, 'Aprovada na 3ª validação; próxima etapa: Pagamento.');
            $ohRepo->log($opId, 'measurement', 'Medição aprovada na 3ª validação. Observações: ' . $notes);
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
                $this->setStatus($opId, self::ST_FINALIZAR, 'Pagamentos verificados; pronto para finalizar.');
                $ohRepo->log($opId, 'payment_checked', '4ª etapa aprovada: pagamentos registrados/verificados. Observações: ' . $notes);
            }
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
            'SELECT mf.*, o.title AS op_title, o.id AS op_id FROM measurement_files mf JOIN operations o ON o.id = mf.operation_id WHERE mf.id = :id'
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
        // notifica o "finalizador" com histórico + link
        $opRepo   = new \App\Repositories\OperationRepository();
        $userRepo = new \App\Repositories\UserRepository();
        $op       = $opRepo->find($opId);
        $finalizerId = (int)($op['payment_finalizer_user_id'] ?? 0);
        if ($finalizerId) {
            if ($u = $userRepo->findBasic($finalizerId)) {
                $base = rtrim($_ENV['APP_URL'] ?? '', '/');
                $link = $base . '/measurements/' . (int)$fileId . '/finalize';
                $opTitle = trim((string)($op['title'] ?? ''));
                $subject = $opTitle . ' — ' . 'Finalizar pagamento';
                // Conteúdo base: resumo da medição
                $html = $this->buildMeasurementSummaryHtml($opId, $fileId);
                // CTA como botão (evita duplicar links)
                $html .= '
                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:16px 0">
                        <tr>
                            <td align="left" style="border-radius:8px; background:#0B2A4A;">
                                <a href="' . $this->esc($link) . '"style="display:inline-block; padding:12px 18px; border-radius:8px; background:#0B2A4A; border:1px solid #0B2A4A; font-family:Arial,Helvetica,sans-serif; font-size:14px; line-height:20px; text-decoration:none; color:#ffffff; font-weight:600;">
                                    Confirmar finalização do pagamento
                                </a>
                            </td>
                        </tr>
                    </table>';
                $this->smtpSend($u['email'], $u['name'], $subject, $html, (int)$opId);
            }
        }
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
        <?php
        // helpers simples para e-mail
        $esc = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $statusBadge = function (?string $st) use ($esc): string {
            $s = mb_strtolower(trim((string)$st), 'UTF-8');
            // cores suaves para e-mail
            $map = [
                'approved' => ['#065f46', '#d1fae5', 'Aprovado'],
                'rejected' => ['#7f1d1d', '#fee2e2', 'Rejeitado'],
                'pending'  => ['#1f2937', '#e5e7eb', 'Pendente'],
                ''         => ['#374151', '#e5e7eb', '—'],
            ];
            $cfg = $map[$s] ?? $map[''];
            return '<span style="display:inline-block;padding:2px 8px;border-radius:999px;font-size:12px; font-weight:600;color:' . $cfg[0] . ';background:' . $cfg[1] . ';line-height:18px;">' . $esc($cfg[2]) . '</span>';
        };
        $stageName = function ($n): string {
            return match ((int)$n) {
                1 => 'Engenharia',
                2 => 'Gestão',
                3 => 'Compliance',
                4 => 'Pagamento',
                default => '—',
            };
        };
        $fmtPtDate = function (?string $v): string {
            if (!$v) return '—';
            $ts = strtotime($v);
            return $ts ? date('d/m/Y', $ts) : $esc($v);
        };
        $thStyle = 'padding:8px 10px;background:#0B2A4A;color:#ffffff;font-size:13px;text-align:left;';
        $tdStyle = 'padding:8px 10px;border-top:1px solid #e5e7eb;font-size:13px;vertical-align:top;';
        $tdRight = $tdStyle . 'text-align:right;';
        ?>

        <div style="font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
            <!-- Cabeçalho da operação -->
            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border:1px solid #e5e7eb;border-radius:12px;overflow:hidden">
                <tr>
                    <td style="padding:14px 16px;background:#f8fafc;">
                        <div style="font-size:16px;font-weight:700;margin:0 0 2px;color:#0b2a4a;">Resumo da Medição</div>
                        <div style="font-size:13px;color:#334155;margin:0;">
                            <strong><?= $esc($op['title'] ?? '') ?></strong>
                            <?php if (!empty($op['code'])): ?>
                                <span style="display:inline-block;margin-left:8px;padding:2px 8px;border-radius:999px;background:#eef2ff;color:#3730a3;font-size:12px;font-weight:600;">
                                    Código: <?= $esc($op['code']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:0 16px 12px;">
                        <!-- Infos rápidas -->
                        <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin-top:10px;border-collapse:collapse;">
                            <tr>
                                <td style="padding:6px 0;font-size:13px;color:#475569;width:160px;">Operação</td>
                                <td style="padding:6px 0;font-size:13px;color:#0f172a;">
                                    #<?= (int)$opId ?> — <?= $esc($op['title'] ?? '') ?>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:6px 0;font-size:13px;color:#475569;">Arquivo</td>
                                <td style="padding:6px 0;font-size:13px;color:#0f172a;">
                                    <?= $esc($file['filename'] ?? '') ?>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
            <!-- Histórico de Análises -->
            <?php if ($reviews): ?>
                <div style="height:12px"></div>
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;border-collapse:separate;">
                    <thead>
                        <thead>
                            <tr>
                                <th style="<?= $thStyle ?>">Etapa</th>
                                <th style="<?= $thStyle ?>">Status</th>
                                <th style="<?= $thStyle ?>">Revisor</th>
                                <th style="<?= $thStyle ?>">Data</th>
                                <th style="<?= $thStyle ?>">Observações</th>
                            </tr>
                        </thead>
                    </thead>
                    <tbody style="background:#ffffff;">
                        <?php foreach ($reviews as $r): ?>
                            <tr>
                                <td style="<?= $tdStyle ?>"><?= $esc($stageName($r['stage'] ?? 0)) ?></td>
                                <td style="<?= $tdStyle ?>"><?= $statusBadge($r['status'] ?? '') ?></td>
                                <td style="<?= $tdStyle ?>"><?= $esc($r['reviewer_name'] ?? '') ?></td>
                                <td style="<?= $tdStyle ?>"><?= $fmtPtDate($r['reviewed_at'] ?? null) ?></td>
                                <td style="<?= $tdStyle ?>"><?= nl2br($esc($r['notes'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <!-- Pagamentos -->
            <?php if ($payments): ?>
                <div style="height:12px"></div>
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;border-collapse:separate;">
                    <thead>
                        <tr>
                            <th style="<?= $thStyle ?>">Data</th>
                            <th style="<?= $thStyle ?>">Método</th>
                            <th style="<?= $thStyle ?>">Observações</th>
                            <th style="<?= $thStyle ?> text-align:right;">Valor</th>
                        </tr>
                    </thead>
                    <tbody style="background:#ffffff;">
                        <?php foreach ($payments as $p): ?>
                            <tr>
                                <td style="<?= $tdStyle ?>">
                                    <?php
                                    $ts = strtotime((string)($p['pay_date'] ?? ''));
                                    echo $ts ? date('d/m/Y', $ts) : '—';
                                    ?>
                                </td>
                                <td style="<?= $tdStyle ?>"><?= $esc($p['method'] ?? '-') ?></td>
                                <td style="<?= $tdStyle ?>"><?= $esc($p['notes']  ?? '-') ?></td>
                                <td style="<?= $tdRight ?>">R$ <?= number_format((float)$p['amount'], 2, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr>
                            <td colspan="3" style="<?= $tdRight ?>; font-weight:700; border-top:2px solid #e5e7eb;">Total</td>
                            <td style="<?= $tdRight ?>; font-weight:700; border-top:2px solid #e5e7eb;">R$ <?= number_format((float)$total, 2, ',', '.') ?></td>
                        </tr>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="height:12px"></div>
                <div style="padding:10px 12px;border:1px dashed #cbd5e1;border-radius:10px;background:#f8fafc;color:#475569;font-size:13px;">
                    <em>Não há pagamentos registrados ainda.</em>
                </div>
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
        $st = $pdo->prepare('SELECT mf.*, o.id AS op_id, o.title AS op_title FROM measurement_files mf JOIN operations o ON o.id = mf.operation_id WHERE mf.id = :id');
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
    /** POST: Confirma finalização => marca OP=Completo e arquivo=Concluído */
    public function finalizeSubmit(int $fileId): void
    {
        $pdo = \Core\Database::pdo();

        $opId = (int)$pdo->query('SELECT operation_id FROM measurement_files WHERE id=' . (int)$fileId)->fetchColumn();
        if (!$opId) {
            http_response_code(404);
            echo 'Operação não encontrada';
            return;
        }

        // 1) Marca a OP como Completo
        $this->setStatus($opId, self::ST_COMPLETO, 'Pagamento finalizado.');
        // 2) Marca a MEDIÇÃO (arquivo) como Concluído
        $st = $pdo->prepare('UPDATE measurement_files SET status = :s, closed_at = NOW() WHERE id = :id');
        $st->execute([':s' => 'Concluído', ':id' => $fileId]);

        // Log
        $oh = new \App\Repositories\OperationHistoryRepository();
        $oh->log($opId, 'payment_finalized', 'Medição #' . (int)$fileId . ' marcada como "Concluído" e operação como "Completo".');

        // ===== NOVO: notificar todos os responsáveis =====
        $opRepo   = new \App\Repositories\OperationRepository();
        $userRepo = new \App\Repositories\UserRepository();
        $op       = $opRepo->find($opId) ?? [];

        // IDs envolvidos na operação
        $recipientsIds = array_filter([
            (int)($op['responsible_user_id']       ?? 0),
            (int)($op['stage2_reviewer_user_id']   ?? 0),
            (int)($op['stage3_reviewer_user_id']   ?? 0),
            (int)($op['payment_manager_user_id']   ?? 0),
            (int)($op['payment_finalizer_user_id'] ?? 0),
        ]);

        // dedup
        $recipientsIds = array_values(array_unique(array_map('intval', $recipientsIds)));

        if ($recipientsIds) {
            $base    = rtrim($_ENV['APP_URL'] ?? '', '/');
            $link    = $base . '/measurements/' . (int)$fileId . '/history';
            $opTitle = trim((string)($op['title'] ?? ''));
            $subject = $opTitle . ' — Medição finalizada';

            // resumo + CTA
            $html  = $this->buildMeasurementSummaryHtml($opId, $fileId);
            $html .= '
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:16px 0">
                <tr>
                    <td align="left" style="border-radius:8px; background:#0B2A4A;">
                        <a href="' . $this->esc($link) . '" style="display:inline-block; padding:12px 18px; border-radius:8px; background:#0B2A4A; border:1px solid #0B2A4A; font-family:Arial,Helvetica,sans-serif; font-size:14px; line-height:20px; text-decoration:none; color:#ffffff; font-weight:600;">
                            Abrir histórico da medição
                        </a>
                    </td>
                </tr>
            </table>';

            foreach ($recipientsIds as $uid) {
                if ($u = $userRepo->findBasic((int)$uid)) {
                    $to = trim((string)($u['email'] ?? ''));
                    if ($to !== '') {
                        $this->smtpSend($to, $u['name'] ?? null, $subject, $html, (int)$opId);
                    }
                }
            }
        }
        // ===== FIM: notificação =====

        // Vai para o histórico da medição
        header('Location: /measurements/' . (int)$fileId . '/history');
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
    /** Envia e-mail com log e histórico da operação (não engole erros). */
    private function smtpSend(string $to, ?string $name, string $subject, string $html, ?int $opId = null): bool
    {
        try {
            Mailer::send($to, $name, $subject, $html);
            if ($opId) {
                (new OperationHistoryRepository())->log($opId, 'mail_sent', "E-mail enviado para {$to}: {$subject}");
            }
            return true;
        } catch (\Throwable $e) {
            $msg = '[SMTP] Falha ao enviar e-mail para ' . $to . ' — ' . $e->getMessage();
            error_log($msg);
            if ($opId) {
                (new OperationHistoryRepository())->log($opId, 'mail_error', $msg);
            }
            $_SESSION['flash_mail_error'] = $e->getMessage();
            return false;
        }
    }
    /** GET: Histórico completo e somente leitura da medição */
    public function history(int $fileId): void
    {
        $pdo = \Core\Database::pdo();

        // arquivo + operação
        $st = $pdo->prepare(
            'SELECT mf.*, o.title AS op_title, o.id AS op_id, o.status AS op_status FROM measurement_files mf JOIN operations o ON o.id = mf.operation_id WHERE mf.id = :id'
        );
        $st->execute([':id' => $fileId]);
        $file = $st->fetch();
        if (!$file) {
            http_response_code(404);
            echo 'Medição não encontrada';
            return;
        }
        $mrRepo  = new \App\Repositories\MeasurementReviewRepository();
        $reviews = $mrRepo->listByFile($fileId);
        $payments = [];
        if (class_exists(\App\Repositories\MeasurementPaymentRepository::class)) {
            $pRepo    = new \App\Repositories\MeasurementPaymentRepository();
            $payments = $pRepo->listByMeasurement($fileId);
        }
        $this->view('measurements/history', [
            'file'        => $file,
            'operationId' => (int)$file['op_id'],
            'reviews'     => $reviews,
            'payments'    => $payments,
        ]);
    }
    /** Quem é o revisor configurado para cada etapa (1..4) */
    private function getStageReviewerId(array $op, int $stage): int
    {
        return match ($stage) {
            1 => (int)($op['responsible_user_id'] ?? 0),
            2 => (int)($op['stage2_reviewer_user_id'] ?? 0),
            3 => (int)($op['stage3_reviewer_user_id'] ?? 0),
            4 => (int)($op['payment_manager_user_id'] ?? 0),
            default => 0,
        };
    }

    /** Quem pode finalizar (Finalizar): finalizer da OP ou admin */
    private function userCanFinalize(?int $uid, array $op): bool
    {
        if (!$uid) return false;
        $user = (new \App\Repositories\UserRepository())->findBasic($uid);
        if (!$user) return false;
        if (($user['role'] ?? '') === 'admin') return true;
        return $uid === (int)($op['payment_finalizer_user_id'] ?? 0);
    }

    public function finalizeReject(int $fileId): void
    {
        $pdo = \Core\Database::pdo();

        // arquivo -> operação
        $st = $pdo->prepare('SELECT operation_id, status FROM measurement_files WHERE id = :id');
        $st->execute([':id' => $fileId]);
        $file = $st->fetch();

        if (!$file) {
            http_response_code(404);
            echo 'Medição não encontrada';
            return;
        }

        $opId = (int)$file['operation_id'];
        $op   = (new OperationRepository())->find($opId);
        if (!$op) {
            http_response_code(404);
            echo 'Operação não encontrada';
            return;
        }

        // precisa estar em "Finalizar" para poder recusar daqui
        if (!$this->statusEquals((string)($op['status'] ?? ''), self::ST_FINALIZAR)) {
            http_response_code(403);
            echo 'Esta ação só está disponível quando a operação está em "Finalizar".';
            return;
        }

        // permissão: finalizador (ou admin)
        $uid = $this->currentUserId();
        if (!$this->userCanFinalize($uid, (array)$op)) {
            http_response_code(403);
            echo 'Você não tem permissão para recusar na etapa de Finalização.';
            return;
        }

        $notes = trim((string)($_POST['notes'] ?? ''));
        if ($notes === '') {
            http_response_code(400);
            echo 'Informe o motivo da recusa.';
            return;
        }

        $ohRepo = new OperationHistoryRepository();

        // marca arquivo como "Rejeitado" (não fecha)
        $pdo->prepare('UPDATE measurement_files SET status = :s WHERE id = :id')
            ->execute([':s' => 'Rejeitado', ':id' => $fileId]);

        // volta OP para "Pagamento"
        $this->setStatus($opId, self::ST_PAGAMENTO, 'Recusada na Finalização. Retorno para Pagamento.');
        $ohRepo->log($opId, 'measurement', 'Medição recusada na etapa de Finalização. Observações: ' . $notes);

        // reabre a ETAPA 4 como pendente
        $pdo->prepare(
            'UPDATE measurement_reviews
            SET status = "pending", reviewed_at = NULL
          WHERE measurement_file_id = :f AND stage = 4'
        )->execute([':f' => $fileId]);

        // notifica assinantes + revisor da etapa anterior (4ª)
        // usamos "4" como etapa para manter a mesma lógica/assunto
        $this->sendRejectionEmails((array)$op, $opId, $fileId, 4, $notes);

        // ficar na mesma página (history dá contexto completo)
        header('Location: /measurements/' . (int)$fileId . '/history');
        exit;
    }

    /**
     * Lista os destinatários “fixos” para RECUSA.
     * Se você já tem uma tabela/feature para “notificar sempre”, ajuste aqui.
     * Exemplo abaixo usa OperationNotifyRepository (seu import já está presente).
     */
    private function listRejectionSubscribers(int $opId): array
    {
        $pdo  = \Core\Database::pdo();
        $subs = [];
        try {
            // Junta assinaturas -> usuários para pegar nome/e-mail
            $sql = 'SELECT u.name, u.email FROM operation_rejection_notify_users r JOIN users u ON u.id = r.user_id WHERE r.operation_id = :op'
                // se users tiver coluna "active", aplica filtro; se não tiver, ignora
                . ($this->columnExists($pdo, 'users', 'active') ? ' AND u.active = 1' : '');
            $st = $pdo->prepare($sql);
            $st->execute([':op' => $opId]);
            $rows = $st->fetchAll() ?: [];
            foreach ($rows as $r) {
                $email = trim((string)($r['email'] ?? ''));
                $name  = trim((string)($r['name'] ?? ''));
                if ($email !== '') {
                    $subs[] = ['email' => $email, 'name' => ($name !== '' ? $name : null)];
                }
            }
        } catch (\Throwable $e) {
            error_log('[listRejectionSubscribers] ' . $e->getMessage());
        }
        // Extras via .env (opcional)
        $extra = trim((string)($_ENV['REJECTION_BCC'] ?? ''));
        if ($extra !== '') {
            foreach (preg_split('/[,;]+/', $extra) as $raw) {
                $email = trim($raw);
                if ($email !== '') {
                    $subs[] = ['email' => $email, 'name' => null];
                }
            }
        }
        // dedup por e-mail
        $uniq = [];
        $out  = [];
        foreach ($subs as $s) {
            $k = strtolower($s['email']);
            if (!isset($uniq[$k])) {
                $uniq[$k] = true;
                $out[] = $s;
            }
        }
        return $out;
    }
    private function columnExists(\PDO $pdo, string $table, string $column): bool
    {
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE " . $pdo->quote($column));
            return (bool)$stmt->fetch();
        } catch (\Throwable $e) {
            return false;
        }
    }
    /** Envia e-mail de recusa para os “assinantes” + (opcional) revisor da etapa anterior */
    private function sendRejectionEmails(array $op, int $opId, int $fileId, int $stage, string $notes): void
    {
        $base = rtrim($_ENV['APP_URL'] ?? '', '/');
        // 1) sempre notificar os “assinantes” de recusa
        $subs = $this->listRejectionSubscribers($opId);
        if ($subs) {
            $stageLabel = match ((int)$stage) {
                1 => self::ST_ENGENHARIA,
                2 => self::ST_GESTAO,
                3 => self::ST_JURIDICO,
                4 => self::ST_PAGAMENTO,
                default => 'Etapa desconhecida',
            };
            $opTitle  = trim((string)($op['title'] ?? ''));
            $subject = 'Ação necessária: revisão da medição da operação ' . $opTitle . ' (etapa ' . $stageLabel . ')';
            $link = $base . '/measurements/' . (int)$fileId . '/history';
            $stageLabel = match ((int)$stage) {
                1 => self::ST_ENGENHARIA,
                2 => self::ST_GESTAO,
                3 => self::ST_JURIDICO,
                4 => self::ST_PAGAMENTO,
                default => 'Etapa desconhecida',
            };
            $cta  = '
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:16px 0">
                    <tr>
                        <td align="left" style="border-radius:8px; background:#0B2A4A;">
                            <a href="' . $this->esc($link) . '" style="display:inline-block; padding:12px 18px; border-radius:8px; background:#0B2A4A; border:1px solid #0B2A4A; font-family:Arial,Helvetica,sans-serif; font-size:14px; line-height:20px; text-decoration:none; color:#ffffff; font-weight:600;">
                                Abrir histórico da medição
                            </a>
                        </td>
                    </tr>
                </table>';
            $html  = '<p>Uma medição foi <strong>reprovada</strong> na operação '
                . '<strong>' . $this->esc((string)$op['title']) . '</strong>'
                . (!empty($op['code']) ? ' (Código: ' . $this->esc((string)$op['code']) . ')' : '')
                . '.</p>';
            $html .= '<p><strong>Validação:</strong> ' . $this->esc($stageLabel) . ' (' . (int)$stage . 'ª)</p>';
            if ($notes !== '') {
                $html .= '<p><strong>Observações:</strong><br>' . nl2br($this->esc($notes)) . '</p>';
            }
            $html .= $cta;
            foreach ($subs as $s) {
                $this->smtpSend($s['email'], $s['name'] ?? null, $subject, $html, $opId);
            }
        }
        // 2) se a recusa NÃO for na 1ª etapa, avisar o revisor da etapa anterior com link de ação
        if ($stage > 1) {
            $prevStage   = $stage - 1;
            $stageName = function (int $n): string {
                return match ($n) {
                    1 => 'Engenharia',
                    2 => 'Gestão',
                    3 => 'Compliance',
                    4 => 'Pagamento',
                    default => $n . 'ª etapa',
                };
            };
            $prevReviewerId = $this->getStageReviewerId($op, $prevStage);
            if ($prevReviewerId > 0) {
                $u = (new UserRepository())->findBasic($prevReviewerId);
                if ($u && !empty($u['email'])) {
                    $opTitle      = (string)($op['title'] ?? '');
                    $opCodeSuffix = !empty($op['code']) ? ' (Código: ' . $this->esc((string)$op['code']) . ')' : '';
                    $rejectedAt   = $stageName($stage);       // onde foi recusada
                    $returnedTo   = $stageName($prevStage);   // para onde voltou
                    $subject = 'Medição da operação ' . $opTitle . ' voltou para a etapa ' . $returnedTo . '';
                    $link = $base . '/measurements/' . $fileId . '/review/' . $prevStage;
                    $cta = '
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:16px 0">
                            <tr>
                                <td align="left" style="border-radius:8px; background:#0B2A4A;">
                                    <a href="' . $this->esc($link) . '" style="display:inline-block; padding:12px 18px; border-radius:8px; background:#0B2A4A; border:1px solid #0B2A4A; font-family:Arial,Helvetica,sans-serif; font-size:14px; line-height:20px; text-decoration:none; color:#ffffff; font-weight:600;">
                                        Reanalisar agora
                                    </a>
                                </td>
                            </tr>
                        </table>';
                    $html  = '<p>Olá, ' . $this->esc((string)$u['name']) . '.</p>';
                    $html .= '<p>A medição da operação <strong>' . $this->esc($opTitle) . '</strong>' . $opCodeSuffix
                        . ' foi <strong>recusada</strong> na etapa <strong>' . $this->esc($rejectedAt)
                        . '</strong> e retornou para a etapa <strong>' . $this->esc($returnedTo) . '</strong>.</p>';
                    if ($notes !== '') {
                        $html .= '<p><strong>Observações da recusa:</strong><br>' . nl2br($this->esc($notes)) . '</p>';
                    }
                    $html .= $cta;
                    $this->smtpSend($u['email'], $u['name'], $subject, $html, $opId);
                }
            }
        }
    }
}
