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

        // Próxima etapa pendente
        $revRepo = new MeasurementReviewRepository();
        foreach ($files as &$f) {
            $f['next_stage'] = $revRepo->nextPendingStage((int)$f['id']) ?? 1;
        }
        unset($f);

        $filesHistory = [];
        if (class_exists(\App\Repositories\MeasurementFileHistoryRepository::class)) {
            $mfhRepo = new \App\Repositories\MeasurementFileHistoryRepository();
            $fileIds = array_map(fn($f) => (int)$f['id'], $files);
            $filesHistory = $mfhRepo->listByFiles($fileIds);
        }

        $displayStatus = $pending ? 'Em aberto' : ucfirst($op['status']);

        $this->view('operations/show', [
            'op'            => $op,
            'history'       => $history,
            'files'         => $files,
            'filesHistory'  => $filesHistory,
            'displayStatus' => $displayStatus,
        ]);
    }

    /** Reaproveita o mesmo form para criar/editar */
    public function create(): void
    {
        $users = (new UserRepository())->allActive();
        $this->view('operations/create', [
            'users' => $users,
            'op'    => null,   // importante para a view saber que é criação
        ]);
    }

    public function store(): void
    {
        // Campos principais
        $title  = trim((string)($_POST['title'] ?? ''));
        $code   = trim((string)($_POST['code']  ?? ''));
        $issuer = trim((string)($_POST['issuer'] ?? ''));
        $due    = trim((string)($_POST['due_date'] ?? ''));
        $amount = trim((string)($_POST['amount'] ?? ''));

        if ($title === '') {
            http_response_code(422);
            echo 'Informe o título.';
            return;
        }

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
        $rejIds = array_map('intval', (array)($_POST['rejection_notify_user_ids'] ?? []));
        $rejIds = array_values(array_unique(array_filter($rejIds)));
        $rejIds = array_slice($rejIds, 0, 2);

        // IMPORTANTE: não enviamos 'status' aqui; deixamos o DEFAULT do banco
        $data = [
            'title'  => $title,
            'code'   => ($code !== '' ? $code : null),
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
        $op = $this->repo->find($id);
        if (!$op) {
            http_response_code(404);
            echo 'Operação não encontrada';
            return;
        }

        $users = (new UserRepository())->allActive();
        $this->view('operations/create', [
            'users' => $users,
            'op'    => $op,   // a view deve pré-selecionar os usuários com base nisso
        ]);
    }

    /** Salva apenas os IDs dos destinatários/validadores */
    public function update(int $id): void
    {
        $op = $this->repo->find($id);
        if (!$op) {
            http_response_code(404);
            echo 'Operação não encontrada';
            return;
        }

        $data = [
            'responsible_user_id'       => (int)($_POST['responsible_user_id'] ?? 0) ?: null,
            'stage2_reviewer_user_id'   => (int)($_POST['stage2_reviewer_user_id'] ?? 0) ?: null,
            'stage3_reviewer_user_id'   => (int)($_POST['stage3_reviewer_user_id'] ?? 0) ?: null,
            'payment_manager_user_id'   => (int)($_POST['payment_manager_user_id'] ?? 0) ?: null,
            'payment_finalizer_user_id' => (int)($_POST['payment_finalizer_user_id'] ?? 0) ?: null,

            // manter coluna antiga nula quando usamos recipients múltiplos
            'rejection_notify_user_id'  => null,
        ];

        // reprovação múltipla no update
        $rejIds = array_map('intval', (array)($_POST['rejection_notify_user_ids'] ?? []));
        $rejIds = array_values(array_unique(array_filter($rejIds)));
        $rejIds = array_slice($rejIds, 0, 2);

        try {
            $this->repo->updateRecipients($id, $data);

            if (!empty($rejIds)) {
                if (class_exists(\App\Repositories\OperationNotifyRepository::class)) {
                    (new \App\Repositories\OperationNotifyRepository())->replaceRecipients($id, $rejIds);
                } else {
                    $this->repo->updateRecipients($id, [
                        'rejection_notify_user_id' => $rejIds[0] ?? null,
                    ]);
                }
            } else {
                if (class_exists(\App\Repositories\OperationNotifyRepository::class)) {
                    (new \App\Repositories\OperationNotifyRepository())->replaceRecipients($id, []);
                } else {
                    $this->repo->updateRecipients($id, ['rejection_notify_user_id' => null]);
                }
            }

            if (class_exists(OperationHistoryRepository::class)) {
                (new OperationHistoryRepository())->log($id, 'recipients_updated', 'Destinatários/validadores atualizados.');
            }
        } catch (\Throwable) {
            http_response_code(500);
            echo 'Falha ao atualizar destinatários.';
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
