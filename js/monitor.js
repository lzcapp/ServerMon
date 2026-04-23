/**
 * ServerMon - AJAX刷新监控
 */

class ServerMonitor {
    constructor() {
        this.interval = parseInt(localStorage.getItem('refreshInterval')) || 5;
        this.timer = null;
        this.previousData = null;
        
        this.init();
    }
    
    init() {
        this.loadSettings();
        this.bindEvents();
        this.fetchData();
        this.startAutoRefresh();
    }
    
    loadSettings() {
        const saved = localStorage.getItem('refreshInterval');
        if (saved) {
            this.interval = parseInt(saved);
        }
    }
    
    bindEvents() {
        // 刷新间隔控制
        document.querySelectorAll('[data-interval]').forEach(btn => {
            btn.addEventListener('click', () => {
                this.setInterval(parseInt(btn.dataset.interval));
            });
        });
        
        // 手动刷新
        document.getElementById('refreshBtn')?.addEventListener('click', () => {
            this.fetchData();
        });
        
        // 主题切换
        document.getElementById('themeToggle')?.addEventListener('click', () => {
            this.toggleTheme();
        });
    }
    
    setInterval(seconds) {
        this.interval = seconds;
        localStorage.setItem('refreshInterval', seconds);
        this.updateIntervalButtons();
        this.startAutoRefresh();
    }
    
    updateIntervalButtons() {
        document.querySelectorAll('[data-interval]').forEach(btn => {
            btn.classList.toggle('active', parseInt(btn.dataset.interval) === this.interval);
        });
    }
    
    toggleTheme() {
        document.body.classList.toggle('light-theme');
        localStorage.setItem('theme', document.body.classList.contains('light-theme') ? 'light' : 'dark');
    }
    
    startAutoRefresh() {
        if (this.timer) {
            clearInterval(this.timer);
        }
        this.timer = setInterval(() => this.fetchData(), this.interval * 1000);
    }
    
    async fetchData() {
        try {
            const response = await fetch('api.php?t=' + Date.now());
            const data = await response.json();
            
            if (data.success) {
                this.updateUI(data);
                this.previousData = data;
            } else {
                console.error('API Error:', data.error);
            }
        } catch (error) {
            console.error('Fetch error:', error);
        }
    }
    
    updateUI(data) {
        // 更新时间
        document.getElementById('updateTime').textContent = new Date().toLocaleTimeString();
        
        // 系统信息
        this.updateElement('hostname', data.system.hostname);
        this.updateElement('os', data.system.os);
        this.updateElement('uptime', this.formatUptime(data.system.uptime));
        this.updateElement('arch', data.system.arch);
        
        // CPU信息
        this.updateElement('cpuModel', data.cpu.model);
        this.updateElement('cpuCores', `${data.cpu.physical_cpus || 1} CPU · ${data.cpu.cores} Cores`);
        this.updateElement('cpuUsage', `${data.cpu.usage}%`);
        this.updateElement('cpuLoad', data.system.load.slice(0, 3).join(' '));
        
        if (data.cpu.temperature) {
            this.updateElement('cpuTemp', `${data.cpu.temperature}°C`);
        }
        
        // 更新CPU使用率条
        this.updateBar('cpuBar', data.cpu.usage);
        this.updateCoreBars(data.cpu.cores_usage || []);
        
        // 内存信息
        const mem = data.memory;
        if (mem && mem.total > 0) {
            this.updateElement('memUsed', `${mem.used} GB`);
            this.updateElement('memTotal', `/ ${mem.total} GB`);
            this.updateBar('memBar', Math.round((mem.used / mem.total) * 100));
        }
        
        // Swap信息
        if (mem.swap_total > 0) {
            const swapPercent = Math.round((mem.swap_used / mem.swap_total) * 100);
            this.updateElement('swapUsed', `${mem.swap_used} GB`);
            this.updateElement('swapTotal', `/ ${mem.swap_total} GB`);
            this.updateBar('swapBar', swapPercent);
        }
        
        // GPU信息
        if (data.gpu) {
            this.updateElement('gpuModel', data.gpu.model);
            
            if (data.gpu.type === 'nvidia') {
                this.updateElement('gpuTemp', `${data.gpu.temperature}°C`);
                this.updateElement('gpuMem', `${data.gpu.memory_used} MB / ${data.gpu.memory_total} MB`);
                this.updateBar('gpuMemBar', data.gpu.memory_usage);
                this.updateElement('gpuUsage', `${data.gpu.gpu_usage}%`);
                this.updateBar('gpuBar', data.gpu.gpu_usage);
            }
        }
        
        // 磁盘信息
        if (data.disks && data.disks.length > 0) {
            const disk = data.disks[0]; // 主磁盘
            this.updateElement('diskMount', disk.mount);
            this.updateElement('diskUsage', `${disk.used} / ${disk.size}`);
            this.updateBar('diskBar', disk.use_percent || 0);
        }
        

    }
    
    updateElement(id, value) {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    }
    
    updateBar(barId, percent) {
        const bar = document.getElementById(barId);
        if (bar) {
            bar.style.width = `${Math.min(100, Math.max(0, percent))}%`;
            
            // 颜色：绿色 < 60%, 黄色 60-80%, 红色 > 80%
            if (percent > 80) {
                bar.className = 'bar-fill danger';
            } else if (percent > 60) {
                bar.className = 'bar-fill warning';
            } else {
                bar.className = 'bar-fill';
            }
        }
    }
    
    updateCoreBars(cores) {
        cores.forEach((usage, index) => {
            const bar = document.getElementById(`coreBar${index}`);
            if (bar) {
                bar.style.width = `${usage}%`;
            }
        });
    }
    
    updateProcesses(processes) {
        const container = document.getElementById('processList');
        if (!container) return;
        
        container.innerHTML = processes.map(p => `
            <div class="process-item">
                <span class="process-cmd">${this.escapeHtml(p.command.substring(0, 40))}</span>
                <span class="process-stat">
                    <span class="process-cpu">${p.cpu.toFixed(1)}%</span>
                    <span class="process-mem">${p.mem.toFixed(1)}%</span>
                </span>
            </div>
        `).join('');
    }
    
    formatUptime(str) {
        return str
            .replace('up ', '')
            .replace(/(\d+) week/g, '$1W')
            .replace(/(\d+) day/g, '$1D')
            .replace(/(\d+) hour/g, '$1H')
            .replace(/(\d+) minute/g, '$1M');
    }
    
    formatBytes(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        if (bytes < 1024 * 1024 * 1024) return (bytes / 1024 / 1024).toFixed(1) + ' MB';
        return (bytes / 1024 / 1024 / 1024).toFixed(2) + ' GB';
    }
    
    escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
}

// 初始化
document.addEventListener('DOMContentLoaded', () => {
    window.monitor = new ServerMonitor();
});
