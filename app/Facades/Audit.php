<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void info(string $message, array $context = [])
 * @method static void warning(string $message, array $context = [])
 * * @see \App\Services\AuditLogger
 */
class Audit extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'audit';
    }
}
