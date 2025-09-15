<?php

declare(strict_types=1);


namespace App\Models;


final class User
{
    public int $id;
    public ?string $entra_object_id = null; // para mapear usuários do Entra ID
    public string $name;
    public string $email;
    public ?string $avatar = null;
    public bool $active = true;
    public string $created_at;
    public string $role = 'user';
}
