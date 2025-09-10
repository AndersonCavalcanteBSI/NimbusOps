<?php

declare(strict_types=1);


namespace App\Models;


final class Operation
{
    public int $id;
    public string $code; // código/identificador da operação
    public string $title; // título
    public string $status; // draft|active|settled|canceled
    public ?string $issuer = null; // emissor/empresa
    public ?string $due_date = null; // Y-m-d
    public ?float $amount = null; // valor total
    public string $created_at;
    public string $updated_at;
}
