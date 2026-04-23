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
    
    if ($IS_LINUX) {
        // 使用 free 命令更可靠
        $freeOutput = shell_exec('free -b 2>/dev/null') ?: '';
        $lines = array_filter(explode("\n", $freeOutput));
        
        if (count($lines) >= 2) {
            // 第一行是标题，第二行是内存
            $memLine = preg_split('/\s+/', trim($lines[1]));
            if (count($memLine) >= 3) {
                $total = round($memLine[1] / 1024 / 1024 / 1024, 2);
                $used = round($memLine[2] / 1024 / 1024 / 1024, 2);
                $free = round($memLine[3] / 1024 / 1024 / 1024, 2);
            }
            
            // 获取更多细节
            $meminfo = shell_exec('cat /proc/meminfo 2>/dev/null') ?: '';
            $memLines = explode("\n", $meminfo);
            $data = [];
            foreach ($memLines as $line) {
                if (strpos($line, ':') !== false) {
                    [$key, $val] = explode(':', $line, 2);
                    $data[trim($key)] = (int) trim(str_replace(' kB', '', $val));
                }
            }
            $buffers = round(($data['Buffers'] ?? 0) / 1024 / 1024, 2);
            $cached = round(($data['Cached'] ?? 0) / 1024 / 1024, 2);
            $available = round(($data['MemAvailable'] ?? $free) / 1024 / 1024, 2);
            
            if ($total > 0 && $used === 0) {
                $used = round($total - $free - $buffers - $cached, 2);
            }
            
            // Swap
            if (count($lines) >= 3) {
                $swapLine = preg_split('/\s+/', trim($lines[2]));
                if (count($swapLine) >= 3) {
                    $swapTotal = round($swapLine[1] / 1024 / 1024 / 1024, 2);
                    $swapUsed = round($swapLine[2] / 1024 / 1024 / 1024, 2);
                }
            }
        }
    } else {
        // macOS
        $total = round((float) shell_exec('sysctl -n hw.memsize') / 1024 / 1024 / 1024, 2);
        $free = round((float) shell_exec('vm_stat | grep "Pages free:" | grep -oE "[0-9]+"') * 4096 / 1024 / 1024 / 1024, 2);
        $available = $free;
        $used = round($total - $free, 2);
    }
    
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
        // 使用 df -B1 获取字节单位，更容易解析
        $output = shell_exec("df -B1 2>/dev/null | grep -v 'tmpfs\|devtmpfs\|udev'") ?: '';
        $lines = array_filter(explode("\n", $output));
        
        foreach ($lines as $i => $line) {
            // 跳过标题行
            if ($i === 0 || strpos($line, 'Filesystem') !== false) continue;
            
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) < 6) continue;
            
            $device = $parts[0];
            // 跳过虚拟文件系统
            if (strpos($device, '/dev/') === false && strpos($device, '/mnt') === false && strpos($device, '/home') === false && strpos($device, '/') !== 0) {
                continue;
            }
            
            $total = (int) $parts[1];
            $used = (int) $parts[2];
            $available = (int) $parts[3];
            $usePercent = (int) str_replace('%', '', $parts[4]);
            $mount = $parts[5];
            
            // 转换大小为 GB
            $totalGB = round($total / 1024 / 1024 / 1024, 1);
            $usedGB = round($used / 1024 / 1024 / 1024, 1);
            
            $disks[] = [
                'device' => $device,
                'mount' => $mount,
                'size' => $totalGB . 'G',
                'used' => $usedGB . 'G',
                'use_percent' => $usePercent,
            ];
        }
    } else {
        // macOS
        $output = shell_exec('df -g 2>/dev/null') ?: '';
        $lines = array_filter(explode("\n", $output));
        
        foreach (array_slice($lines, 1) as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) < 9) continue;
            
            $mount = $parts[count($parts) - 1];
            if (in_array($mount, ['/dev', '/run', '/snap', '/boot'])) continue;
            
            $disks[] = [
                'device' => $parts[0],
                'mount' => $mount,
                'size' => $parts[1] . 'G',
                'used' => $parts[2] . 'G',
                'use_percent' => (int) str_replace('%', '', $parts[4]),
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
        // 使用 ip 命令获取网络接口和IP
        $ipOutput = shell_exec("ip -o link show 2>/dev/null | grep -v 'lo:'") ?: '';
        $ipLines = array_filter(explode("\n", $ipOutput));
        
        foreach ($ipLines as $line) {
            // 提取接口名
            if (preg_match('/^\d+:\s+(\S+):/', $line, $match)) {
                $name = $match[1];
                // 跳过虚拟接口
                if (strpos($name, '@') !== false || strpos($name, '.') !== false) continue;
                
                // 获取IP
                $ip = trim(shell_exec("ip -4 addr show $name 2>/dev/null | grep -oP '(?<=inet\s)\d+(\.\d+){3}' | head -1") ?: '');
                
                // 获取流量
                $netDev = file_get_contents('/proc/net/dev');
                $lines = explode("\n", $netDev);
                $rxBytes = 0;
                $txBytes = 0;
                
                foreach ($lines as $devLine) {
                    if (strpos($devLine, $name . ':') !== false || strpos($devLine, $name) === 0) {
                        $parts = preg_split('/\s+/', trim($devLine));
                        if (count($parts) >= 10) {
                            $rxBytes = (int) $parts[1];
                            $txBytes = (int) $parts[9];
                        }
                        break;
                    }
                }
                
                if ($ip || $rxBytes > 0 || $txBytes > 0) {
                    $networks[] = [
                        'name' => $name,
                        'ip' => $ip,
                        'rx_bytes' => $rxBytes,
                        'tx_bytes' => $txBytes,
                    ];
                }
            }
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
    ];
    
    // cores_usage 需要 Cookie 支持跨请求计算，暂时设为空数组
    $response['cpu']['cores_usage'] = [];
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'error' => $e->getMessage(),
    ];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
