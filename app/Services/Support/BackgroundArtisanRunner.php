<?php

namespace App\Services\Support;

use Illuminate\Support\Facades\Process;

class BackgroundArtisanRunner
{
    public function run(array $arguments, string $logFile = 'background-artisan.log'): void
    {
        $phpBinary = $this->resolveCliPhpBinary();
        $artisan = base_path('artisan');

        if (DIRECTORY_SEPARATOR === '\\') {
            $psCommand = sprintf(
                'Start-Process -FilePath %s -ArgumentList %s -WorkingDirectory %s -WindowStyle Hidden',
                $this->quotePowerShell($phpBinary),
                $this->quotePowerShell($this->buildWindowsArgumentList($artisan, $arguments)),
                $this->quotePowerShell(base_path()),
            );

            Process::path(base_path())
                ->quietly()
                ->run(['powershell', '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-Command', $psCommand]);

            return;
        }

        $command = sprintf(
            '%s %s %s >> %s 2>&1 &',
            escapeshellarg($phpBinary),
            escapeshellarg($artisan),
            implode(' ', array_map('escapeshellarg', $arguments)),
            escapeshellarg(storage_path('logs/' . $logFile)),
        );

        Process::path(base_path())
            ->quietly()
            ->run(['/bin/sh', '-lc', $command]);
    }

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

            if (is_file($candidate) && is_executable($candidate) && ! str_contains(strtolower(basename($candidate)), 'cgi')) {
                return $candidate;
            }
        }

        return 'php';
    }

    private function buildWindowsArgumentList(string $artisan, array $arguments): string
    {
        $parts = array_merge([$artisan], $arguments);

        return implode(' ', array_map(fn (string $part): string => '"' . str_replace('"', '\"', $part) . '"', $parts));
    }

    private function quotePowerShell(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }
}
