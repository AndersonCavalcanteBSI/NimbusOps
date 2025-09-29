<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\Controller;
use App\Repositories\OperationRepository;
use App\Repositories\OperationHistoryRepository;
use App\Repositories\MeasurementReviewRepository;
use App\Repositories\UserRepository;

final class OperationController extends Controller
{
    public function __construct(
        private readonly OperationRepository $repo = new OperationRepository(),
        private readonly OperationHistoryRepository $hist = new OperationHistoryRepository()
    ) {}

    /** Usuário atual (usa DEV_USER_ID apenas em dev) */
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

    private function isAdmin(?int $uid): bool
    {
        if (!$uid) return false;
        $u = (new UserRepository())->findBasic($uid);
        return ($u && ($u['role'] ?? '') === 'admin');
    }

    /** Status exigido por etapa */
    private function requiredStatusForStage(int $stage): ?string
    {
        return match ($stage) {
            1 => 'Engenharia',
            2 => 'Gestão',
            3 => 'Jurídico',
            4 => 'Pagamento',
            default => null,
        };
    }

    public function index(): void
    {
        $filters = [
            'q'      => $_GET['q'] ?? '',
            'status' => $_GET['status'] ?? '',
            'from'   => $_GET['from'] ?? '',
            'to'     => $_GET['to'] ?? '',
        ];
        $page  = (int)($_GET['page'] ?? 1);
        $per   = min(50, max(5, (int)($_GET['per'] ?? 10)));
        $order = $_GET['order'] ?? 'created_at';
        $dir   = $_GET['dir'] ?? 'desc';

        $result = $this->repo->paginate($filters, $page, $per, $order, $dir);

        $this->view('operations/index', [
            'result'  => $result,
            'filters' => $filters,
            'order'   => $order,
            'dir'     => $dir,
        ]);
    }

    public function show(int $id): void
    {
        $op = method_exists($this->repo, 'findWithMetrics')
            ? $this->repo->findWithMetrics($id)
            : $this->repo->find($id);

        if (!$op) {
            http_response_code(404);
            echo 'Operação não encontrada';
            return;
        }

        $history = $this->hist->listByOperation($id);

        $mfRepo = new \App\Repositories\MeasurementFileRepository();
        $files  = $mfRepo->listByOperation($id);
        $pending = $mfRepo->hasPendingAnalysis($id);

        // Próxima etapa pendente + se o usuário pode analisar
        $revRepo = new MeasurementReviewRepository();
        $uid     = $this->currentUserId();
        $baseUrl = rtrim($_ENV['APP_URL'] ?? '', '');

        foreach ($files as &$f) {
            $fileId = (int)$f['id'];

            // Status do arquivo (pode ser "Concluído", etc.)
            $rawFileStatus = (string)($f['status'] ?? '');
            $isConcluded   = mb_strtolower(trim($rawFileStatus), 'UTF-8') === mb_strtolower('Concluído', 'UTF-8');

            // Expor sempre um campo amigável para a view
            $f['file_status'] = $rawFileStatus !== '' ? (string)$rawFileStatus : 'Pendente';

            // Próxima etapa pendente (quando não concluído)
            $next = $revRepo->nextPendingStage($fileId) ?? 1;
            $f['next_stage'] = $next;

            // default
            $f['can_review'] = false;
            unset($f['review_url']); // evita notice quando a view tenta renderizar

            // Link de histórico: sempre preencher para evitar notice na view
            $f['history_url'] = ($baseUrl !== '' ? $baseUrl : '') . '/measurements/' . $fileId . '/history';

            // Se a medição já estiver concluída, não exibir "Analisar"
            if ($isConcluded) {
                continue;
            }

            // status exigido para a etapa
            $required = $this->requiredStatusForStage((int)$next);
            if (!$required || (($op['status'] ?? null) !== $required)) {
                continue;
            }

            // obter a linha da etapa
            $mr = $revRepo->getStage($fileId, (int)$next);
            if (!$mr) {
                continue;
            }
            $revId = (int)($mr['reviewer_user_id'] ?? $mr['reviewer_id'] ?? 0);

            if ($uid && $revId === $uid && ($mr['status'] ?? 'pending') === 'pending') {
                $f['can_review'] = true;
                $f['review_url'] = ($baseUrl !== '' ? $baseUrl : '') . '/measurements/' . $fileId . '/review/' . (int)$next;
            }
        }
        unset($f);

        $filesHistory = [];
        if (class_exists(\App\Repositories\MeasurementFileHistoryRepository::class)) {
            $mfhRepo = new \App\Repositories\MeasurementFileHistoryRepository();
            $fileIds = array_map(fn($f) => (int)$f['id'], $files);
            $filesHistory = $mfhRepo->listByFiles($fileIds);
        }

        // Última medição CONCLUÍDA (prioriza closed_at; fallback uploaded_at)
        $pdo = \Core\Database::pdo();
        $st  = $pdo->prepare(
            'SELECT MAX(COALESCE(closed_at, uploaded_at)) AS last_dt
       FROM measurement_files
      WHERE operation_id = :op AND status = :st'
        );
        $st->execute([':op' => $id, ':st' => 'Concluído']);
        $lastConcludedAt = $st->fetchColumn() ?: null;

        // Fallback extra: caso o repo não esteja trazendo closed_at/status direito
        if (!$lastConcludedAt && !empty($files)) {
            foreach ($files as $f) {
                $isDone = mb_strtolower((string)($f['status'] ?? $f['file_status'] ?? ''), 'UTF-8') === mb_strtolower('Concluído', 'UTF-8');
                if ($isDone) {
                    $dt = $f['closed_at'] ?? $f['uploaded_at'] ?? null;
                    if ($dt && (!$lastConcludedAt || $dt > $lastConcludedAt)) {
                        $lastConcludedAt = $dt;
                    }
                }
            }
        }

        // === Última medição CONCLUÍDA (id + total pago) ===
        $pdo = \Core\Database::pdo();

        // pega o arquivo mais recente concluído (considera closed_at; fallback uploaded_at)
        $st = $pdo->prepare(
            'SELECT id, COALESCE(closed_at, uploaded_at) AS dt
       FROM measurement_files
      WHERE operation_id = :op AND status = :st
   ORDER BY dt DESC
      LIMIT 1'
        );
        $st->execute([':op' => $id, ':st' => 'Concluído']);
        $lastConcludedFile = $st->fetch();

        $lastMeasurementTotal = null;
        if ($lastConcludedFile && !empty($lastConcludedFile['id'])) {
            $fid = (int)$lastConcludedFile['id'];

            // soma os pagamentos dessa medição
            $sum = $pdo->prepare(
                'SELECT SUM(amount) FROM measurement_payments WHERE measurement_file_id = :fid'
            );
            $sum->execute([':fid' => $fid]);
            $lastMeasurementTotal = $sum->fetchColumn();

            // normaliza para float (ou null se não houver pagamentos)
            $lastMeasurementTotal = $lastMeasurementTotal !== null ? (float)$lastMeasurementTotal : null;
        }

        $displayStatus = $pending ? 'Em aberto' : ucfirst($op['status']);

        $this->view('operations/show', [
            'op'                 => $op,
            'history'            => $history,
            'files'              => $files,
            'filesHistory'       => $filesHistory,
            'displayStatus'      => $displayStatus,
            'lastConcludedAt'    => $lastConcludedAt,
            'lastMeasurementTotal' => $lastMeasurementTotal,
        ]);
    }

    /** Reaproveita o mesmo form para criar/editar */
    public function create(): void
    {
        $users = (new UserRepository())->allActive();
        $nextCode = $this->repo->generateNextCode();
        $this->view('operations/create', [
            'users' => $users,
            'op'    => null,
            'nextCode' => $nextCode,
        ]);
    }

    public function store(): void
    {
        // Campos principais
        $title  = trim((string)($_POST['title'] ?? ''));
        //$code   = trim((string)($_POST['code']  ?? ''));
        $issuer = trim((string)($_POST['issuer'] ?? ''));
        $due    = trim((string)($_POST['due_date'] ?? ''));
        $amount = trim((string)($_POST['amount'] ?? ''));

        if ($title === '') {
            http_response_code(422);
            echo 'Informe o título.';
            return;
        }

        // Código é sempre gerado no servidor para evitar corrida
        $code = $this->repo->generateNextCode();

        // Normaliza due_date (aceita dd/mm/aaaa)
        $dueDb = null;
        if ($due !== '') {
            if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $due, $m)) {
                $dueDb = sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
            } else {
                $dueDb = $due; // assume já em YYYY-MM-DD
            }
        }

        // Normaliza amount (remove milhar, vírgula vira ponto)
        $amountDb = null;
        if ($amount !== '') {
            $tmp = preg_replace('/[^\d.,-]/', '', $amount);
            if (strpos($tmp, ',') !== false && strpos($tmp, '.') !== false) {
                $tmp = str_replace('.', '', $tmp); // remove milhar
            }
            $tmp = str_replace(',', '.', $tmp);
            $amountDb = is_numeric($tmp) ? $tmp : null;
        }

        // IDs opcionais -> NULL quando vazios/0
        $r1  = (int)($_POST['responsible_user_id']       ?? 0);
        $r2  = (int)($_POST['stage2_reviewer_user_id']   ?? 0);
        $r3  = (int)($_POST['stage3_reviewer_user_id']   ?? 0);
        $pm  = (int)($_POST['payment_manager_user_id']   ?? 0);
        $fin = (int)($_POST['payment_finalizer_user_id'] ?? 0);

        // Reprovação: múltiplos (até 2)
        $rejIds = array_map('intval', (array)$_POST['rejection_notify_user_ids'] ?? []);
        $rejIds = array_values(array_unique(array_filter($rejIds)));
        $rejIds = array_slice($rejIds, 0, 2);

        // IMPORTANTE: não enviamos 'status' aqui; deixamos o DEFAULT do banco
        $data = [
            'title'  => $title,
            //'code'   => ($code !== '' ? $code : null),
            'code'   => $code,
            'issuer' => $issuer,
            'due_date' => $dueDb,
            'amount'   => $amountDb,

            'responsible_user_id'       => ($r1  ?: null),
            'stage2_reviewer_user_id'   => ($r2  ?: null),
            'stage3_reviewer_user_id'   => ($r3  ?: null),
            'payment_manager_user_id'   => ($pm  ?: null),
            'payment_finalizer_user_id' => ($fin ?: null),

            // coluna legada fica nula; lista múltipla vai à tabela auxiliar
            'rejection_notify_user_id'  => null,
        ];

        try {
            $id = $this->repo->create($data);

            // Persistir recipients de reprovação
            if (!empty($rejIds)) {
                if (class_exists(\App\Repositories\OperationNotifyRepository::class)) {
                    (new \App\Repositories\OperationNotifyRepository())->replaceRecipients($id, $rejIds);
                } else {
                    // Fallback: salva só o primeiro na coluna legada
                    $this->repo->updateRecipients($id, [
                        'rejection_notify_user_id' => $rejIds[0] ?? null,
                    ]);
                }
            }
        } catch (\InvalidArgumentException $e) {
            http_response_code(422);
            echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            return;
        } catch (\PDOException $e) {
            error_log('[operation_create_error] ' . $e->getMessage());

            $msg = $e->getMessage();
            if (stripos($msg, '1062') !== false || stripos($msg, 'duplicate') !== false) {
                http_response_code(409);
                echo 'Falha ao criar operação: código já existe.';
                return;
            }

            if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
                http_response_code(500);
                echo 'Falha ao criar operação: ' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');
            } else {
                http_response_code(500);
                echo 'Falha ao criar operação.';
            }
            return;
        } catch (\Throwable $e) {
            error_log('[operation_create_error_generic] ' . $e->getMessage());
            http_response_code(500);
            echo 'Falha ao criar operação.';
            return;
        }

        if (class_exists(OperationHistoryRepository::class)) {
            (new OperationHistoryRepository())->log($id, 'created', 'Operação criada via formulário.');
        }

        header('Location: /operations/' . $id);
        exit;
    }

    /** Form de edição (mesma view, com $op preenchida) */
    public function edit(int $id): void
    {
        // (opcional) exige admin
        if (!$this->isAdmin($this->currentUserId())) {
            http_response_code(403);
            echo 'Acesso negado.';
            return;
        }

        $op = $this->repo->find($id);
        if (!$op) {
            http_response_code(404);
            echo 'Operação não encontrada';
            return;
        }

        $users = (new UserRepository())->allActive();

        // CSRF simples (one-time)
        $csrf = bin2hex(random_bytes(16));
        $_SESSION['csrf_token'] = $csrf;

        $this->view('operations/edit', [
            'users' => $users,
            'op'    => $op,
            'csrf'  => $csrf,
        ]);
    }

    /** Salva apenas os IDs dos destinatários/validadores */
    public function update(int $id): void
    {
        if (!$this->isAdmin($this->currentUserId())) {
            http_response_code(403);
            echo 'Acesso negado.';
            return;
        }

        $sent = (string)($_POST['csrf_token'] ?? '');
        $good = (string)($_SESSION['csrf_token'] ?? '');
        unset($_SESSION['csrf_token']);
        if ($sent === '' || $good === '' || !hash_equals($good, $sent)) {
            http_response_code(419);
            echo 'Sessão expirada. Recarregue a página e tente novamente.';
            return;
        }

        $op = $this->repo->find($id);
        if (!$op) {
            http_response_code(404);
            echo 'Operação não encontrada';
            return;
        }

        // -------- Campos editáveis --------
        $title = trim((string)($_POST['title'] ?? ''));
        $next  = trim((string)($_POST['next_measurement_at'] ?? ''));

        if ($title === '') {
            http_response_code(422);
            echo 'Preencha o título.';
            return;
        }

        // Normaliza próxima medição (YYYY-MM-DD ou vazio)
        $nextDb = null;
        if ($next !== '') {
            $ts = strtotime($next);
            $nextDb = $ts ? date('Y-m-d', $ts) : null;
        }

        // -------- Responsáveis por etapa --------
        $dataRecipients = [
            'responsible_user_id'       => (int)($_POST['responsible_user_id'] ?? 0) ?: null,
            'stage2_reviewer_user_id'   => (int)($_POST['stage2_reviewer_user_id'] ?? 0) ?: null,
            'stage3_reviewer_user_id'   => (int)($_POST['stage3_reviewer_user_id'] ?? 0) ?: null,
            'payment_manager_user_id'   => (int)($_POST['payment_manager_user_id'] ?? 0) ?: null,
            'payment_finalizer_user_id' => (int)($_POST['payment_finalizer_user_id'] ?? 0) ?: null,
            'rejection_notify_user_id'  => null,
        ];

        // Reprovação múltipla (até 2)
        $rejIds = array_map('intval', (array)($_POST['rejection_notify_user_ids'] ?? []));
        $rejIds = array_values(array_unique(array_filter($rejIds)));
        $rejIds = array_slice($rejIds, 0, 2);

        try {
            // 1) ATENÇÃO: atualiza apenas os campos permitidos (NÃO altera code/status)
            if (method_exists($this->repo, 'updateCore')) {
                $this->repo->updateCore($id, [
                    'title'               => $title,
                    'next_measurement_at' => $nextDb,
                ]);
            } else {
                // Fallback direto em SQL caso não tenha updateCore() no repo
                $pdo = \Core\Database::pdo();
                $sql = 'UPDATE operations
                       SET title = :t,
                           next_measurement_at = :n
                     WHERE id = :id';
                $pdo->prepare($sql)->execute([
                    ':t'  => $title,
                    ':n'  => $nextDb,
                    ':id' => $id,
                ]);
            }

            // 2) destinatários/validadores
            $this->repo->updateRecipients($id, $dataRecipients);

            // 3) lista múltipla de notificação de recusa
            if (class_exists(\App\Repositories\OperationNotifyRepository::class)) {
                (new \App\Repositories\OperationNotifyRepository())->replaceRecipients($id, $rejIds);
            } else {
                $this->repo->updateRecipients($id, [
                    'rejection_notify_user_id' => $rejIds[0] ?? null,
                ]);
            }

            if (class_exists(OperationHistoryRepository::class)) {
                (new OperationHistoryRepository())->log($id, 'recipients_updated', 'Destinatários/validadores atualizados.');
                (new OperationHistoryRepository())->log($id, 'updated', 'Dados editáveis da operação atualizados.');
            }
        } catch (\Throwable $e) {
            error_log('[operation_update_error] ' . $e->getMessage());
            http_response_code(500);
            echo 'Falha ao atualizar a operação.';
            return;
        }

        header('Location: /operations/' . $id);
        exit;
    }

    /**
     * Compat legado: agora redireciona à tela de revisão do arquivo.
     */
    public function analyzeFile(int $fileId): void
    {
        header('Location: /measurements/' . (int)$fileId . '/review');
        exit;
    }
}
