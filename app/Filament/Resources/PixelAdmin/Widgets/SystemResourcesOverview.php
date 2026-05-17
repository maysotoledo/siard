<?php

namespace App\Filament\Resources\PixelAdmin\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SystemResourcesOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Recursos do servidor';

    protected ?string $description = 'Uso atual de memória, armazenamento e processador.';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $memory = $this->memoryUsage();
        $disk = $this->diskUsage();
        $cpuUsage = $this->cpuUsagePercentage();

        return [
            Stat::make('Memória', $memory['used_percentage'] === null ? 'Indisponível' : $memory['used_percentage'] . '% usada')
                ->description(sprintf(
                    'Instalada: %s | Usada: %s | Disponível: %s',
                    $this->formatBytes($memory['total']),
                    $this->formatBytes($memory['used']),
                    $this->formatBytes($memory['available']),
                ))
                ->descriptionIcon('heroicon-m-server')
                ->color($this->usageColor($memory['used_percentage'])),

            Stat::make('HD', $disk['used_percentage'] === null ? 'Indisponível' : $disk['used_percentage'] . '% usado')
                ->description(sprintf(
                    'Total: %s | Usado: %s | Disponível: %s',
                    $this->formatBytes($disk['total']),
                    $this->formatBytes($disk['used']),
                    $this->formatBytes($disk['available']),
                ))
                ->descriptionIcon('heroicon-m-circle-stack')
                ->color($this->usageColor($disk['used_percentage'])),

            Stat::make('Processador', $cpuUsage === null ? 'Indisponível' : $cpuUsage . '% em uso')
                ->description('Consumo atual do processador')
                ->descriptionIcon('heroicon-m-cpu-chip')
                ->color($this->usageColor($cpuUsage)),
        ];
    }

    /**
     * @return array{total: int|null, used: int|null, available: int|null, used_percentage: int|null}
     */
    private function memoryUsage(): array
    {
        $linuxMemory = $this->linuxMemoryUsage();

        if ($linuxMemory['total'] !== null) {
            return $linuxMemory;
        }

        return $this->macMemoryUsage();
    }

    /**
     * @return array{total: int|null, used: int|null, available: int|null, used_percentage: int|null}
     */
    private function linuxMemoryUsage(): array
    {
        if (! is_readable('/proc/meminfo')) {
            return $this->emptyUsage();
        }

        $meminfo = file('/proc/meminfo', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $values = [];

        foreach ($meminfo ?: [] as $line) {
            if (preg_match('/^([A-Za-z_()]+):\s+(\d+)\s+kB$/', $line, $matches) !== 1) {
                continue;
            }

            $values[$matches[1]] = ((int) $matches[2]) * 1024;
        }

        $total = $values['MemTotal'] ?? null;
        $available = $values['MemAvailable'] ?? null;

        if ($total === null || $available === null) {
            return $this->emptyUsage();
        }

        $used = max(0, $total - $available);

        return [
            'total' => $total,
            'used' => $used,
            'available' => $available,
            'used_percentage' => $this->percentage($used, $total),
        ];
    }

    /**
     * @return array{total: int|null, used: int|null, available: int|null, used_percentage: int|null}
     */
    private function macMemoryUsage(): array
    {
        if (! function_exists('shell_exec')) {
            return $this->emptyUsage();
        }

        $totalOutput = trim((string) @shell_exec('sysctl -n hw.memsize 2>/dev/null'));
        $vmStatOutput = (string) @shell_exec('vm_stat 2>/dev/null');

        if ($totalOutput === '' || $vmStatOutput === '') {
            return $this->emptyUsage();
        }

        $total = (int) $totalOutput;
        $pageSize = 4096;

        if (preg_match('/page size of (\d+) bytes/', $vmStatOutput, $matches) === 1) {
            $pageSize = (int) $matches[1];
        }

        $freePages = 0;
        $inactivePages = 0;

        if (preg_match('/Pages free:\s+(\d+)\./', $vmStatOutput, $matches) === 1) {
            $freePages = (int) $matches[1];
        }

        if (preg_match('/Pages inactive:\s+(\d+)\./', $vmStatOutput, $matches) === 1) {
            $inactivePages = (int) $matches[1];
        }

        if ($total <= 0) {
            return $this->emptyUsage();
        }

        $available = min($total, ($freePages + $inactivePages) * $pageSize);
        $used = max(0, $total - $available);

        return [
            'total' => $total,
            'used' => $used,
            'available' => $available,
            'used_percentage' => $this->percentage($used, $total),
        ];
    }

    /**
     * @return array{total: int|null, used: int|null, available: int|null, used_percentage: int|null}
     */
    private function diskUsage(): array
    {
        $path = base_path();
        $total = disk_total_space($path);
        $available = disk_free_space($path);

        if ($total === false || $available === false || $total <= 0) {
            return $this->emptyUsage();
        }

        $used = max(0, $total - $available);

        return [
            'total' => (int) $total,
            'used' => (int) $used,
            'available' => (int) $available,
            'used_percentage' => $this->percentage((int) $used, (int) $total),
        ];
    }

    private function cpuUsagePercentage(): ?int
    {
        $linuxUsage = $this->linuxCpuUsagePercentage();

        if ($linuxUsage !== null) {
            return $linuxUsage;
        }

        return $this->macCpuUsagePercentage();
    }

    private function linuxCpuUsagePercentage(): ?int
    {
        if (! is_readable('/proc/stat')) {
            return null;
        }

        $first = $this->readLinuxCpuTimes();
        usleep(100000);
        $second = $this->readLinuxCpuTimes();

        if ($first === null || $second === null) {
            return null;
        }

        $idleDelta = $second['idle'] - $first['idle'];
        $totalDelta = $second['total'] - $first['total'];

        if ($totalDelta <= 0) {
            return null;
        }

        return max(0, min(100, (int) round((1 - ($idleDelta / $totalDelta)) * 100)));
    }

    /**
     * @return array{idle: int, total: int}|null
     */
    private function readLinuxCpuTimes(): ?array
    {
        $line = file('/proc/stat', FILE_IGNORE_NEW_LINES)[0] ?? null;

        if ($line === null || preg_match('/^cpu\s+(.+)$/', $line, $matches) !== 1) {
            return null;
        }

        $times = array_map('intval', preg_split('/\s+/', trim($matches[1])) ?: []);

        if (count($times) < 5) {
            return null;
        }

        return [
            'idle' => ($times[3] ?? 0) + ($times[4] ?? 0),
            'total' => array_sum($times),
        ];
    }

    private function macCpuUsagePercentage(): ?int
    {
        if (! function_exists('shell_exec')) {
            return null;
        }

        $logicalCpus = (int) trim((string) @shell_exec('sysctl -n hw.logicalcpu 2>/dev/null'));
        $cpuOutput = (string) @shell_exec("ps -A -o %cpu= 2>/dev/null | awk '{sum += $1} END {print sum}'");

        if ($logicalCpus <= 0 || trim($cpuOutput) === '') {
            return null;
        }

        return max(0, min(100, (int) round(((float) $cpuOutput) / $logicalCpus)));
    }

    /**
     * @return array{total: null, used: null, available: null, used_percentage: null}
     */
    private function emptyUsage(): array
    {
        return [
            'total' => null,
            'used' => null,
            'available' => null,
            'used_percentage' => null,
        ];
    }

    private function percentage(int $value, int $total): int
    {
        if ($total <= 0) {
            return 0;
        }

        return max(0, min(100, (int) round(($value / $total) * 100)));
    }

    private function formatBytes(?int $bytes): string
    {
        if ($bytes === null) {
            return 'Indisponível';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float) $bytes;
        $unit = 0;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return number_format($value, $unit === 0 ? 0 : 1, ',', '.') . ' ' . $units[$unit];
    }

    private function usageColor(?int $percentage): string
    {
        return match (true) {
            $percentage === null => 'gray',
            $percentage >= 90 => 'danger',
            $percentage >= 75 => 'warning',
            default => 'success',
        };
    }
}
