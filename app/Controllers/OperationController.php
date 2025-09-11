<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\Controller;
use App\Repositories\OperationRepository;
use App\Repositories\OperationHistoryRepository;

final class OperationController extends Controller
{
    public function __construct(
        private readonly OperationRepository $repo = new OperationRepository(),
        private readonly OperationHistoryRepository $hist = new OperationHistoryRepository()
    ) {}

    public function index(): void
    {
        $filters = [
            'q' => $_GET['q'] ?? '',
            'status' => $_GET['status'] ?? '',
            'from' => $_GET['from'] ?? '',
            'to' => $_GET['to'] ?? '',
        ];
        $page  = (int)($_GET['page'] ?? 1);
        $per   = min(50, max(5, (int)($_GET['per'] ?? 10)));
        $order = $_GET['order'] ?? 'created_at';
        $dir   = $_GET['dir'] ?? 'desc';

        // remove a variável $repo redundante
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
        // Se não tiver findWithMetrics(), troque por find()
        $op = method_exists($this->repo, 'findWithMetrics')
            ? $this->repo->findWithMetrics($id)
            : $this->repo->find($id);

        if (!$op) {
            http_response_code(404);
            echo 'Operação não encontrada';
            return;
        }

        $history = $this->hist->listByOperation($id);

        // Arquivos de medição
        $mfRepo = new \App\Repositories\MeasurementFileRepository();
        $files  = $mfRepo->listByOperation($id);
        $pending = $mfRepo->hasPendingAnalysis($id);

        // Histórico por arquivo (se o repo existir)
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

    /**
     * Fluxo antigo: marque como analisado ⇒ REMOVER.
     * Agora, a análise deve acontecer na tela /measurements/{fileId}/review.
     * Mantemos este método apenas para compatibilidade, redirecionando.
     */
    public function analyzeFile(int $fileId): void
    {
        // Compatibilidade: em vez de marcar analisado, leva para a tela de revisão
        header('Location: /measurements/' . (int)$fileId . '/review');
        exit;
    }
}
