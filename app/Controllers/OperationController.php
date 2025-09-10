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
        $page = (int)($_GET['page'] ?? 1);
        $per = min(50, max(5, (int)($_GET['per'] ?? 10)));
        $order = $_GET['order'] ?? 'created_at';
        $dir = $_GET['dir'] ?? 'desc';


        $result = $repo = $this->repo->paginate($filters, $page, $per, $order, $dir);


        $this->view('operations/index', [
            'result' => $result,
            'filters' => $filters,
            'order' => $order,
            'dir' => $dir,
        ]);
    }


    public function show(int $id): void
    {
        $op = $this->repo->findWithMetrics($id);
        if (!$op) {
            http_response_code(404);
            echo 'Operação não encontrada';
            return;
        }

        // histórico geral da operação (já existia)
        $history = $this->hist->listByOperation($id);

        // arquivos de medição + pendências
        $mfRepo  = new \App\Repositories\MeasurementFileRepository();
        $mfhRepo = new \App\Repositories\MeasurementFileHistoryRepository();

        $files = $mfRepo->listByOperation($id);
        $pending = $mfRepo->hasPendingAnalysis($id);

        // histórico por arquivo (mapeado por fileId)
        $fileIds = array_map(fn($f) => (int)$f['id'], $files);
        $filesHistory = $mfhRepo->listByFiles($fileIds);

        // status exibido: "Em aberto" se tiver arquivo pendente; senão mantém o da operação
        $displayStatus = $pending ? 'Em aberto' : ucfirst($op['status']);

        $this->view('operations/show', [
            'op'            => $op,
            'history'       => $history,
            'files'         => $files,
            'filesHistory'  => $filesHistory,
            'displayStatus' => $displayStatus,
        ]);
    }
}
