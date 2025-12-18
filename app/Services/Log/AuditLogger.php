<?php

namespace App\Services\Log;

use Illuminate\Support\Facades\Log;

class AuditLogger
{
    public function info(string $message, array $context = []): void
    {
        $this->writeLog('info', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->writeLog('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->writeLog('error', $message, $context);
    }

    protected function writeLog(string $level, $message, array $context): void
    {
        $standardMeta = [
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'url' => request()->fullUrl()
        ];

        Log::log($level, $message, array_merge($standardMeta, $context));
    }
}
