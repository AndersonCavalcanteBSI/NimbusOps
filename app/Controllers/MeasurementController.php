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
    private const ST_JURIDICO   = 'Jurídico';
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

    /** Verifica se o usuário pode revisar esta etapa */
    /*private function userCanReview(int $uid, int $expectedReviewerId): bool
    {
        $userRepo = new UserRepository();
        $user = $userRepo->find($uid); // usa find, não findBasic

        if (!$user) {
            return false;
        }

        $isAdmin = ($user['role'] ?? '') === 'admin';
        error_log("[userCanReview] uid={$uid}, expected={$expectedReviewerId}, role=" . ($user['role'] ?? ''));
        return $uid === $expectedReviewerId || $isAdmin;
    }*/

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
            $by  = 'sistema';
            if ($uid) {
                $u = (new \App\Repositories\UserRepository())->findBasic($uid);
                if ($u && !empty($u['name'])) {
                    $by = $u['name'];
                }
            }
            $decor = "\n\n--- " . date('Y-m-d H:i:s') . " por " . $by . " ---\n" . $notes;
        }

        if ($decor === '') {
            // Sem novas notas: não mexe no campo notes
            $sql = 'UPDATE measurement_reviews
                   SET status = :st,
                       reviewed_at = NOW()
                 WHERE measurement_file_id = :f AND stage = :s';
            $pdo->prepare($sql)->execute([
                ':st' => $newStatus,
                ':f'  => $fileId,
                ':s'  => $stage,
            ]);
        } else {
            // Com novas notas: concatena ao notes existente
            $sql = 'UPDATE measurement_reviews
                   SET status = :st,
                       reviewed_at = NOW(),
                       notes = CONCAT(COALESCE(notes, ""), :decor)
                 WHERE measurement_file_id = :f AND stage = :s';
            $pdo->prepare($sql)->execute([
                ':st'    => $newStatus,
                ':decor' => $decor,
                ':f'     => $fileId,
                ':s'     => $stage,
            ]);
        }
    }

    /** ===== NOVO: garante que uma etapa exista e esteja PENDENTE ===== */
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
            $pdo->prepare('UPDATE measurement_reviews
                              SET status = "pending", reviewed_at = NULL
                            WHERE measurement_file_id = :f AND stage = :s')
                ->execute([':f' => $fileId, ':s' => $stage]);
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
                $subject = $this->mailSubject($op, 'Nova medição — 1ª Validação: Engenharia');
                $html = '<p>Olá, ' . htmlspecialchars($u['name']) . '</p>'
                    . '<p>Um novo arquivo de medição foi adicionado à operação '
                    . '<strong>#' . $opId . ($op['code'] ? ' (' . $this->esc($op['code']) . ')' : '') . '</strong> '
                    . '(' . htmlspecialchars((string)$op['title']) . ').</p>'
                    . '<p>Status da operação: <strong>' . self::ST_ENGENHARIA . '</strong>.</p>'
                    . '<p><a href="' . htmlspecialchars($link) . '">Clique aqui para analisar</a>.</p>';
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
    /*public function reviewForm(int $fileId, int $stage = 1): void
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

            /*if (!$reviewerId) {
                $reviewerId = (int)($pdo->query('SELECT id FROM users WHERE active = 1 ORDER BY id ASC LIMIT 1')->fetchColumn() ?: 0);
            }*/

    /*if ($reviewerId) {
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

        if (!$readOnly && $uid) {
            if ($requiredSt && !$this->statusEquals((string)($op['status'] ?? ''), $requiredSt)) {
                http_response_code(403);
                echo 'Esta etapa não está disponível no status atual da operação.';
                return;
            }

            /*$expectedReviewerId = $this->reviewerIdFrom((array)$mr);
            if ($uid !== $expectedReviewerId) {
                if (strtolower((string)($_ENV['APP_DEBUG'] ?? 'false')) === 'true') {
                    error_log(sprintf(
                        '[reviewForm] Bloqueado: uid=%s, expected=%s, stage=%d, file=%d',
                        var_export($uid, true),
                        var_export($expectedReviewerId, true),
                        (int)$stage,
                        (int)$fileId
                    ));
                    echo 'Você não é o revisor desta etapa. (debug uid=' . (int)$uid . ' expected=' . (int)$expectedReviewerId . ')';
                } else {
                    echo 'Você não é o revisor desta etapa.';
                }
                http_response_code(403);
                return;
            }*/

    /*$expectedReviewerId = $this->reviewerIdFrom((array)$mr);
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
        }*/

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

    /*// revisões anteriores
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
    }*/

    /** POST: Recebe decisão (Aprovar/Reprovar) com observações */
    /*public function reviewSubmit(int $fileId, int $stage = 1): void
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
                /*if (!$reviewerId) {
                    $reviewerId = (int)($pdo->query('SELECT id FROM users WHERE active = 1 ORDER BY id ASC LIMIT 1')->fetchColumn() ?: 0);
                }*/
    /*if ($reviewerId) {
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

        if ($uid) {
            if ($requiredSt && !$this->statusEquals((string)($opCheck['status'] ?? ''), $requiredSt)) {
                http_response_code(403);
                echo 'Esta etapa não está disponível no status atual da operação.';
                return;
            }

            /*$expectedReviewerId = $this->reviewerIdFrom((array)$stageRow);
            if ($uid !== $expectedReviewerId) {
                if (strtolower((string)($_ENV['APP_DEBUG'] ?? 'false')) === 'true') {
                    error_log(sprintf(
                        '[reviewSubmit] Bloqueado: uid=%s, expected=%s, stage=%d, file=%d',
                        var_export($uid, true),
                        var_export($expectedReviewerId, true),
                        (int)$stage,
                        (int)$fileId
                    ));
                    echo 'Você não é o revisor desta etapa. (debug uid=' . (int)$uid . ' expected=' . (int)$expectedReviewerId . ')';
                } else {
                    echo 'Você não é o revisor desta etapa.';
                }
                http_response_code(403);
                return;
            }*/

    /*$expectedReviewerId = $this->reviewerIdFrom((array)$stageRow);
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
        }

        // ======= AQUI: não sobrescreve mais as notas =======
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
            // === Reprovação: volta status e reabre etapa anterior ===
            switch ($stage) {
                case 1:
                    $newStatus = self::ST_REJEITADO;
                    $note      = "Medição reprovada na 1ª validação.";
                    break;
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
                    $note      = "Medição reprovada na 4ª validação. Retorno para Jurídico.";
                    break;
                default:
                    $newStatus = self::ST_PAGAMENTO;
                    $note      = "Medição reprovada na {$stage}ª validação. Retorno para Financeiro/Pagamento.";
                    break;
            }

            // Atualiza status da OP e registra log
            $this->setStatus($opId, $newStatus, $note);
            $ohRepo->log($opId, 'measurement', $note . ' Observações: ' . $notes);

            // Reabrir a etapa anterior (mantendo a recusa nesta etapa)
            if ($stage > 1) {
                $prevStage = $stage - 1;
                $pdo->prepare(
                    'UPDATE measurement_reviews
                        SET status = "pending", reviewed_at = NULL
                      WHERE measurement_file_id = :f AND stage = :s'
                )->execute([':f' => $fileId, ':s' => $prevStage]);
            }

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
                    $link = $base . '/measurements/' . $fileId . '/review/2';
                    $subject = $this->mailSubject($op, '2ª validação — nova medição');
                    $html = '<p>Olá, ' . $this->esc($u['name']) . '</p>'
                        . '<p>Há uma nova medição para análise na <strong>2ª validação: Gestão</strong> da operação '
                        . '<strong>#' . $opId . ($op['code'] ? ' (' . $this->esc($op['code']) . ')' : '') . '</strong> '
                        . '(' . $this->esc((string)$op['title']) . ').</p>'
                        . '<p><a href="' . $this->esc($link) . '">Clique aqui para analisar</a>.</p>';
                    $this->smtpSend($u['email'], $u['name'], $subject, $html, $opId);
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
                    $link = $base . '/measurements/' . $fileId . '/review/3';
                    $subject = $this->mailSubject($op, '3ª validação — nova medição');
                    $html = '<p>Olá, ' . $this->esc($u['name']) . '</p>'
                        . '<p>Há uma nova medição para análise na <strong>3ª validação: Jurídico</strong> da operação '
                        . '<strong>#' . $opId . ($op['code'] ? ' (' . $this->esc($op['code']) . ')' : '') . '</strong> '
                        . '(' . $this->esc((string)$op['title']) . ').</p>'
                        . '<p><a href="' . $this->esc($link) . '">Clique aqui para analisar</a>.</p>';
                    $this->smtpSend($u['email'], $u['name'], $subject, $html, $opId);
                }
            }
            $this->setStatus($opId, self::ST_JURIDICO, 'Aprovada na 2ª validação; próxima etapa: Jurídico.');
            $ohRepo->log($opId, 'measurement', 'Medição aprovada na 2ª validação. Observações: ' . $notes);
        } elseif ($stage === 3) {
            // Garante ETAPA 4 pendente
            $pmId = (int)($op['payment_manager_user_id'] ?? 0);
            if ($pmId) {
                $this->ensureStagePending($fileId, 4, $pmId);
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
                    $this->smtpSend($u['email'], $u['name'], $subject, $html, $opId);
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
    }*/

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

        /*if ($decision === 'rejected') {
            // === Reprovação: volta status e reabre etapa anterior ===
            switch ($stage) {
                case 1:
                    $newStatus = self::ST_REJEITADO;
                    $note      = "Medição reprovada na 1ª validação.";
                    break;
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
                    $note      = "Medição reprovada na 4ª validação. Retorno para Jurídico.";
                    break;
                default:
                    $newStatus = self::ST_PAGAMENTO;
                    $note      = "Medição reprovada na {$stage}ª validação. Retorno para Financeiro/Pagamento.";
                    break;
            }

            // Atualiza status da OP e registra log
            /*$this->setStatus($opId, $newStatus, $note);
            $ohRepo->log($opId, 'measurement', $note . ' Observações: ' . $notes);

            // Reabrir a etapa anterior (mantendo a recusa nesta etapa)
            /*if ($stage > 1) {
                $prevStage = $stage - 1;
                $pdo->prepare(
                    'UPDATE measurement_reviews
                        SET status = "pending", reviewed_at = NULL
                      WHERE measurement_file_id = :f AND stage = :s'
                )->execute([':f' => $fileId, ':s' => $prevStage]);
            }*/

        // >>> NOVO: marca o ARQUIVO como Rejeitado
        /*$pdo->prepare('UPDATE measurement_files SET status = :s WHERE id = :id')
                ->execute([':s' => 'Rejeitado', ':id' => $fileId]);

            // Atualiza status da OP e registra log
            $this->setStatus($opId, $newStatus, $note);
            $ohRepo->log($opId, 'measurement', $note . ' Observações: ' . $notes);

            // Reabrir a etapa anterior (mantendo a recusa nesta etapa)
            if ($stage > 1) {
                $prevStage = $stage - 1;
                $pdo->prepare(
                    'UPDATE measurement_reviews
            SET status = "pending", reviewed_at = NULL
          WHERE measurement_file_id = :f AND stage = :s'
                )->execute([':f' => $fileId, ':s' => $prevStage]);
            }

            header('Location: /operations/' . $opId);
            exit;
        }*/

        if ($decision === 'rejected') {
            // Marca o arquivo como Rejeitado
            $pdo->prepare('UPDATE measurement_files SET status = :s WHERE id = :id')
                ->execute([':s' => 'Rejeitado', ':id' => $fileId]);

            if ($stage === 1) {
                // 1ª etapa: operação inteira rejeitada
                $newStatus = self::ST_REJEITADO;
                $note      = "Medição reprovada na 1ª validação (Engenharia).";

                $this->setStatus($opId, $newStatus, $note);
                $ohRepo->log($opId, 'measurement', $note . ' Observações: ' . $notes);
            } else {
                // Demais etapas: volta para a anterior
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
                        $note      = "Medição reprovada na 4ª validação. Retorno para Jurídico.";
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
                $pdo->prepare(
                    'UPDATE measurement_reviews
                SET status = "pending", reviewed_at = NULL
              WHERE measurement_file_id = :f AND stage = :s'
                )->execute([':f' => $fileId, ':s' => $prevStage]);
            }

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
                    $link = $base . '/measurements/' . $fileId . '/review/2';
                    $subject = $this->mailSubject($op, '2ª validação — nova medição');
                    $html = '<p>Olá, ' . $this->esc($u['name']) . '</p>'
                        . '<p>Há uma nova medição para análise na <strong>2ª validação: Gestão</strong> da operação '
                        . '<strong>#' . $opId . ($op['code'] ? ' (' . $this->esc($op['code']) . ')' : '') . '</strong> '
                        . '(' . $this->esc((string)$op['title']) . ').</p>'
                        . '<p><a href="' . $this->esc($link) . '">Clique aqui para analisar</a>.</p>';
                    $this->smtpSend($u['email'], $u['name'], $subject, $html, $opId);
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
                    $link = $base . '/measurements/' . $fileId . '/review/3';
                    $subject = $this->mailSubject($op, '3ª validação — nova medição');
                    $html = '<p>Olá, ' . $this->esc($u['name']) . '</p>'
                        . '<p>Há uma nova medição para análise na <strong>3ª validação: Jurídico</strong> da operação '
                        . '<strong>#' . $opId . ($op['code'] ? ' (' . $this->esc($op['code']) . ')' : '') . '</strong> '
                        . '(' . $this->esc((string)$op['title']) . ').</p>'
                        . '<p><a href="' . $this->esc($link) . '">Clique aqui para analisar</a>.</p>';
                    $this->smtpSend($u['email'], $u['name'], $subject, $html, $opId);
                }
            }
            $this->setStatus($opId, self::ST_JURIDICO, 'Aprovada na 2ª validação; próxima etapa: Jurídico.');
            $ohRepo->log($opId, 'measurement', 'Medição aprovada na 2ª validação. Observações: ' . $notes);
        } elseif ($stage === 3) {
            // Garante ETAPA 4 pendente
            $pmId = (int)($op['payment_manager_user_id'] ?? 0);
            if ($pmId) {
                $this->ensureStagePending($fileId, 4, $pmId);
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
                    $this->smtpSend($u['email'], $u['name'], $subject, $html, $opId);
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

        // notifica o "finalizador" com histórico + link
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

                $this->smtpSend($u['email'], $u['name'], $subject, $html, $opId);
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
            'SELECT mf.*, o.title AS op_title, o.id AS op_id, o.status AS op_status
           FROM measurement_files mf
           JOIN operations o ON o.id = mf.operation_id
          WHERE mf.id = :id'
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
}
