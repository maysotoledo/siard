<?php

namespace App\Services\Queue;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class QueueWorkerStarter
{
    private function resolveCliPhpBinary(): string
    {
        $phpBinary = PHP_BINARY;
        $phpDir = dirname($phpBinary);
        $phpBase = basename($phpBinary);
        $phpCliFromCgi = preg_replace('/php-cgi(\.exe)?$/i', 'php$1', $phpBinary);

        $candidates = array_values(array_unique(array_filter([
            $phpCliFromCgi,
            str_contains($phpBase, 'fpm')
                ? $phpDir . DIRECTORY_SEPARATOR . str_replace('-fpm', '', $phpBase)
                : $phpBinary,
            str_contains(strtolower($phpBase), 'cgi')
                ? $phpDir . DIRECTORY_SEPARATOR . str_ireplace('php-cgi', 'php', $phpBase)
                : null,
            $phpDir . DIRECTORY_SEPARATOR . 'php',
            $phpDir . DIRECTORY_SEPARATOR . 'php.exe',
            $phpBinary,
            'php',
        ], fn ($candidate) => is_string($candidate) && $candidate !== '')));

        foreach ($candidates as $candidate) {
            if ($candidate === 'php') {
                return $candidate;
            }

            if (is_file($candidate) && is_executable($candidate) && ! str_contains(basename($candidate), 'fpm')) {
                return $candidate;
            }
        }

        return 'php';
    }

    public function start(): void
    {
        $phpBinary = $this->resolveCliPhpBinary();
        $connection = (string) config('queue.default', 'database');
        $logPath = storage_path('logs/queue-worker.log');

        Log::channel('agenda_mail')->warning('Worker residente ausente; iniciando worker temporario.', [
            'php_binary_web' => PHP_BINARY,
            'php_binary_queue' => $phpBinary,
            'queue_connection' => $connection,
        ]);

        if (DIRECTORY_SEPARATOR === '\\') {
            $artisan = base_path('artisan');
            $psCommand = sprintf(
                'New-Item -ItemType Directory -Force -Path %s | Out-Null; Start-Process -FilePath %s -ArgumentList %s -WorkingDirectory %s -WindowStyle Hidden -RedirectStandardOutput %s -RedirectStandardError %s',
                $this->quotePowerShell(dirname($logPath)),
                $this->quotePowerShell($phpBinary),
                $this->quotePowerShell($this->buildWindowsArgumentList($artisan, $connection)),
                $this->quotePowerShell(base_path()),
                $this->quotePowerShell($logPath),
                $this->quotePowerShell($logPath),
            );

            Process::path(base_path())
                ->quietly()
                ->run(['powershell', '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-Command', $psCommand]);

            return;
        }

        $command = sprintf(
            '%s artisan queue:work %s --stop-when-empty --queue=default --tries=3 --timeout=300 >> %s 2>&1 &',
            escapeshellarg($phpBinary),
            escapeshellarg($connection),
            escapeshellarg($logPath),
        );

        Process::path(base_path())
            ->quietly()
            ->run(['/bin/sh', '-lc', $command]);
    }

    private function buildWindowsArgumentList(string $artisan, string $connection): string
    {
        $parts = [
            $artisan,
            'queue:work',
            $connection,
            '--stop-when-empty',
            '--queue=default',
            '--tries=3',
            '--timeout=300',
        ];

        return implode(' ', array_map(fn (string $part): string => '"' . str_replace('"', '\"', $part) . '"', $parts));
    }

    private function quotePowerShell(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }
}
