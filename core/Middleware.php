<?php
declare(strict_types=1);


namespace Core;


type MiddlewareHandler = callable;


interface Middleware
{
public function handle(): void;
}