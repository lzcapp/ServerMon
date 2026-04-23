<?php
/**
 * ServerMon API - 获取服务器监控数据
 * 支持 Linux 和 macOS
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// 平台检测
$IS_LINUX = PHP_OS === 'Linux';

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

// 安全执行命令（Linux专用）
function execLinux(string $cmd): string {
    global $IS_LINUX;
    if (!$IS_LINUX) return '';
    return shell_exec($cmd . ' 2>/dev/null') ?: '';
}

// 获取系统信息
function getSystemInfo(): array {
    global $IS_LINUX;
    
    $hostname = clean(shell_exec('hostname') ?: 'Unknown');
    
    $os = 'Unknown';
    if ($IS_LINUX && file_exists('/etc/os-release')) {
        $osRelease = file_get_contents('/etc/os-release');
        if (preg_match('/PRETTY_NAME="([^"]+)"/', $osRelease, $matches)) {
            $os = $matches[1];
        }
    } elseif (!$IS_LINUX) {
        $os = clean(shell_exec('sw_vers -productName') ?: '') . ' ' . clean(shell_exec('sw_vers -productVersion') ?: '');
    }
    
    $uptime = '';
    $load = ['0.00', '0.00', '0.00'];
    $arch = clean(shell_exec('uname -m') ?: '');
    
    if ($IS_LINUX) {
        $uptime = clean(shell_exec('uptime -p') ?: '');
        $loadStr = clean(shell_exec('cat /proc/loadavg') ?: '');
        $load = $loadStr ? explode(' ', $loadStr) : $load;
    } else {
        $bootTime = shell_exec('sysctl -n kern.boottime | grep -oE "[0-9]+" | head -1') ?: '';
        if ($bootTime) {
            $uptimeSeconds = time() - (int)$bootTime;
            $days = floor($uptimeSeconds / 86400);
            $hours = floor(($uptimeSeconds % 86400) / 3600);
            $uptime = "up {$days} days, {$hours} hours";
        }
        $loadStr = shell_exec('sysctl -n vm.loadavg') ?: '';
        $loadStr = str_replace('{', '', str_replace('}', '', $loadStr));
        $load = array_values(array_filter(explode(' ', $loadStr))) ?: $load;
    }
    
    return [
        'hostname' => $hostname,
        'os' => $os ?: 'Unknown',
        'uptime' => $uptime ?: 'Unknown',
        'load' => $load,
        'arch' => $arch,
    ];
}

// 获取CPU信息
function getCpuInfo(): array {
    global $IS_LINUX;
    
    $model = 'Unknown';
    $physicalCpus = 1;
    $cores = 1;
    $temp = 0;
    $usage = 0;
    
    if ($IS_LINUX) {
        // CPU型号
        $model = clean(shell_exec("grep -i 'model name' /proc/cpuinfo 2>/dev/null | head -1 | sed 's/Model name://'") ?: '');
        if (!$model) {
            $model = clean(shell_exec("grep -i 'Hardware' /proc/cpuinfo 2>/dev/null | head -1 | sed 's/Hardware://'") ?: 'Unknown');
        }
        
        // 核心数
        $physicalCpus = (int) clean(shell_exec('cat /proc/cpuinfo | grep "physical id" | sort -u | wc -l'));
        if ($physicalCpus === 0) $physicalCpus = 1;
        $cores = (int) clean(shell_exec('nproc') ?: '1');
        
        // 温度
        $tempFile = glob('/sys/class/thermal/thermal_zone*/temp');
        if (!empty($tempFile) && is_readable($tempFile[0])) {
            $temp = round((float) file_get_contents($tempFile[0]) / 1000, 1);
        }
        
        // CPU使用率
        $stat = clean(shell_exec("cat /proc/stat | head -1") ?: '');
        $current = array_values(array_filter(explode(' ', $stat)));
        $prev = getPrevCpuData('core0');
        
        if ($prev && count($current) >= 5) {
            $prevIdle = (int) ($prev[4] ?? 0);
            $currIdle = (int) ($current[4] ?? 0);
            
            $prevTotal = 0;
            for ($i = 1; $i < count($prev); $i++) {
                $prevTotal += (int) ($prev[$i] ?? 0);
            }
            
            $currTotal = 0;
            for ($i = 1; $i < count($current); $i++) {
                $currTotal += (int) ($current[$i] ?? 0);
            }
            
            $idleDiff = $currIdle - $prevIdle;
            $totalDiff = $currTotal - $prevTotal;
            
            if ($totalDiff > 0) {
                $usage = round(100 * ($totalDiff - $idleDiff) / $totalDiff);
            }
        }
        
        if (count($current) >= 5) {
            saveCpuData('core0', $current);
        }
    } else {
        // macOS
        $model = clean(shell_exec('sysctl -n machdep.cpu.brand_string') ?: 'Apple Silicon');
        $cores = (int) clean(shell_exec('sysctl -n hw.ncpu') ?: '1');
        $physicalCpus = 1;
    }
    
    $model = str_replace(['(R)', '(TM)'], '', $model);
    
    return [
        'model' => trim($model) ?: 'Unknown',
        'physical_cpus' => max(1, $physicalCpus),
        'cores' => max(1, $cores),
        'temperature' => $temp,
        'usage' => $usage,
    ];
}

// 获取内存信息
function getMemoryInfo(): array {
    global $IS_LINUX;
    
    $total = $free = $buffers = $cached = $available = $swapTotal = $swapUsed = 0;
    
    if ($IS_LINUX && is_readable('/proc/meminfo')) {
        $meminfo = file_get_contents('/proc/meminfo');
        $lines = explode("\n", $meminfo);
        
        $data = [];
        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                [$key, $val] = explode(':', $line, 2);
                $data[trim($key)] = (int) trim($val);
            }
        }
        
        $total = ($data['MemTotal'] ?? 0) / 1024 / 1024;
        $free = ($data['MemFree'] ?? 0) / 1024 / 1024;
        $buffers = ($data['Buffers'] ?? 0) / 1024 / 1024;
        $cached = ($data['Cached'] ?? 0) / 1024 / 1024;
        $available = ($data['MemAvailable'] ?? $free) / 1024 / 1024;
        $swapTotal = ($data['SwapTotal'] ?? 0) / 1024 / 1024;
        $swapFree = ($data['SwapFree'] ?? 0) / 1024 / 1024;
        $swapUsed = $swapTotal - $swapFree;
    } else {
        // macOS
        $total = round((float) shell_exec('sysctl -n hw.memsize') / 1024 / 1024 / 1024, 2);
        $free = round((float) shell_exec('vm_stat | grep "Pages free:" | grep -oE "[0-9]+"') * 4096 / 1024 / 1024 / 1024, 2);
        $available = $free;
    }
    
    $used = $total - $free - $buffers - $cached;
    
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

// 获取GPU信息
function getGpuInfo(): ?array {
    global $IS_LINUX;
    
    if ($IS_LINUX) {
        $lspci = shell_exec("lspci 2>/dev/null | grep -i vga") ?: '';
        if (empty(trim($lspci))) {
            return null;
        }
        
        $model = clean(preg_replace('/^.*: /', '', $lspci));
        
        if (strpos($model, 'NVIDIA') !== false && file_exists('/usr/bin/nvidia-smi')) {
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
        
        return [
            'model' => $model,
            'type' => 'other',
        ];
    } else {
        // macOS
        $gpuType = clean(shell_exec('system_profiler SPDisplaysAgentType_Output 2>/dev/null | grep "Chipset Model" | sed "s/.*: //"') ?: '');
        if ($gpuType) {
            return [
                'model' => $gpuType,
                'type' => 'apple',
            ];
        }
    }
    
    return null;
}

// 获取磁盘信息
function getDiskInfo(): array {
    global $IS_LINUX;
    
    $disks = [];
    
    if ($IS_LINUX) {
        $output = shell_exec("df -h -P 2>/dev/null | grep -wv tmpfs | grep -wv devtmpfs | grep -wv 'udev'") ?: '';
        $lines = array_filter(explode("\n", $output));
        
        foreach ($lines as $line) {
            $parts = array_values(array_filter(explode(' ', $line)));
            if (count($parts) < 6) continue;
            
            $disks[] = [
                'device' => $parts[0],
                'mount' => $parts[5],
                'size' => $parts[1],
                'used' => $parts[2],
                'use_percent' => (int) str_replace('%', '', $parts[4]),
            ];
        }
    } else {
        // macOS
        $output = shell_exec('df -h 2>/dev/null') ?: '';
        $lines = array_filter(explode("\n", $output));
        
        foreach (array_slice($lines, 1) as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) < 9) continue;
            
            $mount = $parts[count($parts) - 1];
            if (in_array($mount, ['/dev', '/run', '/snap', '/boot'])) continue;
            
            $size = $parts[1];
            $used = $parts[3];
            $usePercent = (int) str_replace('%', '', $parts[4]);
            
            $disks[] = [
                'device' => $parts[0],
                'mount' => $mount,
                'size' => $size,
                'used' => $used,
                'use_percent' => $usePercent,
            ];
        }
    }
    
    return $disks;
}

// 获取网络信息
function getNetworkInfo(): array {
    global $IS_LINUX;
    
    $networks = [];
    
    if ($IS_LINUX) {
        $output = shell_exec("cat /proc/net/dev 2>/dev/null | grep -v 'lo:' | tail -n +3") ?: '';
        $lines = array_filter(explode("\n", $output));
        
        foreach ($lines as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) < 10) continue;
            
            $name = rtrim($parts[0], ':');
            $ip = trim(shell_exec("ip -4 addr show $name 2>/dev/null | grep -oP '(?<=inet\s)\d+(\.\d+){3}' | head -1") ?: '');
            
            $networks[] = [
                'name' => $name,
                'ip' => $ip,
                'rx_bytes' => (int) $parts[1],
                'tx_bytes' => (int) $parts[9],
            ];
        }
    } else {
        // macOS
        $output = shell_exec('ifconfig 2>/dev/null') ?: '';
        $interfaces = preg_split('/\n(?=[a-z])/', $output);
        
        foreach ($interfaces as $iface) {
            if (preg_match('/^(\w+):/', $iface, $match) && !in_array($match[1], ['lo', 'lo0'])) {
                $name = $match[1];
                $ip = preg_match('/inet (\d+\.\d+\.\d+\.\d+)/', $iface, $ipMatch) ? $ipMatch[1] : '';
                
                $networks[] = [
                    'name' => $name,
                    'ip' => $ip,
                    'rx_bytes' => 0,
                    'tx_bytes' => 0,
                ];
            }
        }
    }
    
    return $networks;
}

// 获取进程列表
function getTopProcesses(int $limit = 5): array {
    $processes = [];
    
    if (PHP_OS === 'Linux') {
        $output = shell_exec("ps aux --sort=-%cpu 2>/dev/null | head -n " . ($limit + 1)) ?: '';
        $lines = array_filter(explode("\n", $output));
        
        foreach (array_slice($lines, 1, $limit) as $line) {
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
    } else {
        $output = shell_exec("ps aux -r 2>/dev/null | head -n $limit") ?: '';
        $lines = array_filter(explode("\n", $output));
        
        foreach ($lines as $line) {
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
    }
    
    return $processes;
}

// 主程序
try {
    $response = [
        'success' => true,
        'timestamp' => time(),
        'platform' => PHP_OS,
        'system' => getSystemInfo(),
        'cpu' => getCpuInfo(),
        'memory' => getMemoryInfo(),
        'gpu' => getGpuInfo(),
        'disks' => getDiskInfo(),
        'network' => getNetworkInfo(),
        'processes' => getTopProcesses(),
    ];
    
    $response['cpu']['cores_usage'] = [];
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'error' => $e->getMessage(),
    ];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
