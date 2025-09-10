<?php

declare(strict_types=1);


namespace App\Models;


final class OperationHistory
{
    public int $id;
    public int $operation_id;
    public string $action; // created|updated|status_changed
    public string $notes;
    public string $created_at;
}
