<?php
/**
 * ServerMon API - 获取服务器监控数据
 * 返回 JSON 格式的监控数据
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// 获取上一次CPU数据（用于计算使用率）
function getPrevCpuData(string $key): ?array {
    if (isset($_COOKIE[$key])) {
        $data = json_decode($_COOKIE[$key], true);
        return is_array($data) ? $data : null;
    }
    return null;
}

// 保存CPU数据到Cookie
function saveCpuData(string $key, array $data): void {
    setcookie($key, json_encode($data), time() + 3600, '/', '', false, true);
}

// 清理字符串
function clean(string $str): string {
    return trim(str_replace(["\r\n", "\r", "\n", "\t"], '', $str));
}

// 获取系统信息
function getSystemInfo(): array {
    return [
        'hostname' => clean(shell_exec('hostname') ?: ''),
        'os' => clean(preg_replace('/PRETTY_NAME="(.*)"/', '$1', shell_exec('cat /etc/os-release | grep PRETTY_NAME') ?: '')),
        'uptime' => clean(shell_exec('uptime -p') ?: ''),
        'load' => explode(' ', clean(shell_exec('cat /proc/loadavg') ?: '')),
        'arch' => clean(shell_exec('arch') ?: ''),
    ];
}

// 获取CPU信息
function getCpuInfo(): array {
    // CPU型号
    $model = clean(preg_replace('/Model name:\s*/', '', shell_exec("lscpu | grep 'Model name'") ?: ''));
    $model = str_replace(['(R)', '(TM)'], '', $model);
    
    // 核心数
    $physicalCpus = (int) clean(shell_exec('cat /proc/cpuinfo | grep "physical id" | sort | uniq | wc -l'));
    $cores = (int) clean(shell_exec('cat /proc/cpuinfo | grep processor | wc -l'));
    
    // 温度
    $temp = shell_exec("cat /sys/class/thermal/thermal_zone*/temp 2>/dev/null | head -1");
    $temp = $temp ? round((float) clean($temp) / 1000, 1) : 0;
    
    // CPU使用率
    $stat = clean(shell_exec("cat /proc/stat | head -1") ?: '');
    $current = array_values(array_filter(explode(' ', $stat)));
    
    $prev = getPrevCpuData('core0');
    $usage = 0;
    
    if ($prev && count($current) >= 11) {
        $prevIdle = (int) $prev[4];
        $currIdle = (int) $current[4];
        
        $prevTotal = 0;
        for ($i = 1; $i <= 10; $i++) {
            $prevTotal += (int) ($prev[$i] ?? 0);
        }
        
        $currTotal = 0;
        for ($i = 1; $i <= 10; $i++) {
            $currTotal += (int) ($current[$i] ?? 0);
        }
        
        $idleDiff = $currIdle - $prevIdle;
        $totalDiff = $currTotal - $prevTotal;
        
        $usage = $totalDiff > 0 ? round(100 * ($totalDiff - $idleDiff) / $totalDiff) : 0;
    }
    
    if (count($current) >= 11) {
        saveCpuData('core0', $current);
    }
    
    return [
        'model' => $model,
        'physical_cpus' => $physicalCpus,
        'cores' => $cores,
        'temperature' => $temp,
        'usage' => $usage,
    ];
}

// 获取单个CPU核心使用率
function getCoreUsage(array $statLine, int $index): int {
    $current = array_values(array_filter(explode(' ', $statLine)));
    $prev = getPrevCpuData('core' . $index);
    
    $usage = 0;
    
    if ($prev && count($current) >= 8) {
        $prevTotal = 0;
        for ($i = 1; $i <= 7; $i++) {
            $prevTotal += (int) ($prev[$i] ?? 0);
        }
        
        $currTotal = 0;
        for ($i = 1; $i <= 7; $i++) {
            $currTotal += (int) ($current[$i] ?? 0);
        }
        
        $idleDiff = (int) ($current[3] ?? 0) - (int) ($prev[3] ?? 0);
        $totalDiff = $currTotal - $prevTotal;
        
        $usage = $totalDiff > 0 ? round(100 * ($totalDiff - $idleDiff) / $totalDiff) : 0;
    }
    
    if (count($current) >= 8) {
        saveCpuData('core' . $index, $current);
    }
    
    return min(100, max(0, $usage));
}

// 获取内存信息
function getMemoryInfo(): array {
    $meminfo = shell_exec('cat /proc/meminfo') ?: '';
    $lines = explode("\n", $meminfo);
    
    $data = [];
    foreach ($lines as $line) {
        if (strpos($line, ':') !== false) {
            [$key, $val] = explode(':', $line, 2);
            $data[$key] = (int) trim($val);
        }
    }
    
    $total = ($data['MemTotal'] ?? 0) / 1024 / 1024;
    $free = ($data['MemFree'] ?? 0) / 1024 / 1024;
    $buffers = ($data['Buffers'] ?? 0) / 1024 / 1024;
    $cached = ($data['Cached'] ?? 0) / 1024 / 1024;
    $available = ($data['MemAvailable'] ?? $free) / 1024 / 1024;
    $used = $total - $free - $buffers - $cached;
    
    $swapTotal = ($data['SwapTotal'] ?? 0) / 1024 / 1024;
    $swapFree = ($data['SwapFree'] ?? 0) / 1024 / 1024;
    $swapUsed = $swapTotal - $swapFree;
    
    return [
        'total' => round($total, 2),
        'used' => round($used, 2),
        'free' => round($free, 2),
        'available' => round($available, 2),
        'buffers' => round($buffers, 2),
        'cached' => round($cached, 2),
        'swap_total' => round($swapTotal, 2),
        'swap_used' => round($swapUsed, 2),
    ];
}

// 获取NVIDIA GPU信息
function getGpuInfo(): ?array {
    $lspci = shell_exec("lspci | grep VGA") ?: '';
    
    if (empty(trim($lspci))) {
        return null;
    }
    
    $model = clean(preg_replace('/^.*: /', '', $lspci));
    
    if (strpos($model, 'NVIDIA') === false) {
        return [
            'model' => $model,
            'type' => 'other',
        ];
    }
    
    // NVIDIA GPU
    $temp = clean(shell_exec('nvidia-smi --query-gpu=temperature.gpu --format=csv,noheader') ?: '');
    $memUsed = (int) clean(str_replace('MiB', '', shell_exec('nvidia-smi --query-gpu=memory.used --format=csv,noheader') ?: ''));
    $memTotal = (int) clean(str_replace('MiB', '', shell_exec('nvidia-smi --query-gpu=memory.total --format=csv,noheader') ?: ''));
    $gpuUsage = (int) clean(str_replace('%', '', shell_exec('nvidia-smi --query-gpu=utilization.gpu --format=csv,noheader') ?: ''));
    $memUsage = (int) clean(str_replace('%', '', shell_exec('nvidia-smi --query-gpu=utilization.memory --format=csv,noheader') ?: ''));
    
    return [
        'model' => $model,
        'type' => 'nvidia',
        'temperature' => (int) $temp,
        'memory_used' => $memUsed,
        'memory_total' => $memTotal,
        'gpu_usage' => $gpuUsage,
        'memory_usage' => $memUsage,
    ];
}

// 获取磁盘信息
function getDiskInfo(): array {
    $output = shell_exec("df -h -P | grep -wv tmpfs | grep -wv devtmpfs") ?: '';
    $lines = array_filter(explode("\n", $output));
    
    $disks = [];
    foreach ($lines as $line) {
        $parts = array_values(array_filter(explode(' ', $line)));
        if (count($parts) < 6) continue;
        
        $size = $parts[1];
        $used = $parts[2];
        $usePercent = (int) str_replace('%', '', $parts[4]);
        $mount = $parts[5];
        
        $disks[] = [
            'device' => $parts[0],
            'mount' => $mount,
            'size' => $size,
            'used' => $used,
            'use_percent' => $usePercent,
        ];
    }
    
    return $disks;
}

// 获取网络流量
function getNetworkInfo(): array {
    $output = shell_exec("cat /proc/net/dev | grep -v 'lo:' | tail -n +3") ?: '';
    $lines = array_filter(explode("\n", $output));
    
    $networks = [];
    foreach ($lines as $line) {
        $parts = preg_split('/\s+/', trim($line));
        if (count($parts) < 10) continue;
        
        $name = $parts[0];
        $rxBytes = (int) $parts[1];
        $txBytes = (int) $parts[9];
        
        // 获取IP
        $ip = trim(shell_exec("ip -4 addr show $name 2>/dev/null | grep -oP '(?<=inet\s)\d+(\.\d+){3}' | head -1") ?: '');
        
        $networks[] = [
            'name' => $name,
            'ip' => $ip,
            'rx_bytes' => $rxBytes,
            'tx_bytes' => $txBytes,
        ];
    }
    
    return $networks;
}

// 获取进程列表（按CPU排序）
function getTopProcesses(int $limit = 5): array {
    $output = shell_exec("ps aux --sort=-%cpu | head -n " . ($limit + 1)) ?: '';
    $lines = array_filter(explode("\n", array_slice(explode("\n", $output), 1)));
    
    $processes = [];
    foreach (array_slice($lines, 0, $limit) as $line) {
        $parts = preg_split('/\s+/', trim($line));
        if (count($parts) < 11) continue;
        
        $processes[] = [
            'user' => $parts[0],
            'pid' => $parts[1],
            'cpu' => (float) $parts[2],
            'mem' => (float) $parts[3],
            'command' => implode(' ', array_slice($parts, 10)),
        ];
    }
    
    return $processes;
}

// 主程序 - 组装所有数据
try {
    $response = [
        'success' => true,
        'timestamp' => time(),
        'system' => getSystemInfo(),
        'cpu' => getCpuInfo(),
        'memory' => getMemoryInfo(),
        'gpu' => getGpuInfo(),
        'disks' => getDiskInfo(),
        'network' => getNetworkInfo(),
        'processes' => getTopProcesses(),
    ];
    
    // 获取各核心使用率
    $cpuStat = shell_exec("cat /proc/stat | grep cpu") ?: '';
    $cpuLines = array_filter(explode("\n", $cpuStat));
    $cores = [];
    foreach (array_slice($cpuLines, 1) as $index => $line) {
        $cores[] = getCoreUsage($line, $index + 1);
    }
    $response['cpu']['cores'] = $cores;
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'error' => $e->getMessage(),
    ];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
