<?php

declare(strict_types=1);


namespace Core;


abstract class Controller
{
    protected function view(string $path, array $data = []): void
    {
        extract($data);
        require __DIR__ . '/../app/Views/' . $path . '.php';
    }
}
