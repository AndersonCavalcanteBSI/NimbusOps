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
        $code    = trim((string)($_POST['code']  ?? ''));
        $title   = trim((string)($_POST['title'] ?? ''));
        $issuer  = trim((string)($_POST['issuer'] ?? ''));
        $due     = trim((string)($_POST['due_date'] ?? ''));
        $amount  = trim((string)($_POST['amount'] ?? ''));

        if ($code === '' || $title === '') {
            http_response_code(422);
            echo 'Informe ao menos Código e Título.';
            return;
        }

        $data = [
            'code'   => $code,
            'title'  => $title,
            'issuer' => $issuer,
            'due_date' => $due !== '' ? $due : null,
            'amount' => $amount,
            'status' => 'draft',
            // destinatários (podem ser nulos)
            'responsible_user_id'       => (int)($_POST['responsible_user_id'] ?? 0) ?: null,
            'stage2_reviewer_user_id'   => (int)($_POST['stage2_reviewer_user_id'] ?? 0) ?: null,
            'stage3_reviewer_user_id'   => (int)($_POST['stage3_reviewer_user_id'] ?? 0) ?: null,
            'payment_manager_user_id'   => (int)($_POST['payment_manager_user_id'] ?? 0) ?: null,
            'payment_finalizer_user_id' => (int)($_POST['payment_finalizer_user_id'] ?? 0) ?: null,
            'rejection_notify_user_id'  => (int)($_POST['rejection_notify_user_id'] ?? 0) ?: null,
        ];

        try {
            $id = $this->repo->create($data);
        } catch (\InvalidArgumentException $e) {
            http_response_code(422);
            echo htmlspecialchars($e->getMessage());
            return;
        } catch (\Throwable) {
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
            'rejection_notify_user_id'  => (int)($_POST['rejection_notify_user_id'] ?? 0) ?: null,
        ];

        try {
            $this->repo->updateRecipients($id, $data);
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
