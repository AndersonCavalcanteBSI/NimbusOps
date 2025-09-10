<?php

declare(strict_types=1);


namespace Core;


use Dotenv\Dotenv;


final class Env
{
    public static function load(string $root): void
    {
        if (is_file($root . '/.env')) {
            $dotenv = Dotenv::createImmutable($root);
            $dotenv->safeLoad();
        }
    }
}
