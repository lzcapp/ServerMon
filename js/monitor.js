/**
 * ServerMon - AJAX刷新监控 (Classic Terminal Style)
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

        // 恢复主题
        const theme = localStorage.getItem('theme');
        if (theme === 'light') {
            document.documentElement.classList.add('light-theme');
            document.body.classList.add('light-theme');
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
        const isLight = document.documentElement.classList.toggle('light-theme');
        document.body.classList.toggle('light-theme', isLight);
        localStorage.setItem('theme', isLight ? 'light' : 'dark');
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
        this.updateText('updateTime', new Date().toLocaleTimeString());

        // 系统信息（纯文本）
        this.updateText('hostname', data.system.hostname);
        this.updateText('os', data.system.os);
        this.updateText('uptime', this.formatUptime(data.system.uptime));
        this.updateText('arch', data.system.arch);

        // CPU模块
        const cpu = data.cpu;
        this.updateElement('cpuModelInfo', `${this.escapeHtml(cpu.model)} (${this.escapeHtml(data.system.arch)})`);

        const stats = [];
        if (cpu.physical_cpus > 0) {
            stats.push(`${cpu.physical_cpus}<span class="unit">CPU</span>`);
        }
        stats.push(`${cpu.cores}<span class="unit">Cores</span>`);
        stats.push(`${cpu.usage}<span class="unit">%</span>`);
        const load = (data.system.load || []).slice(0, 3).join(' ');
        if (load) {
            stats.push(`<span>(${load})</span>`);
        }
        if (cpu.temperature) {
            stats.push(`${Math.round(cpu.temperature)}<span class="unit">°C</span>`);
        }
        this.updateElement('cpuStats', stats.join('&nbsp;'));

        // CPU使用率条（单色）
        this.renderBar('cpuBar', [{ cls: 'usr', count: cpu.usage }]);

        // 每核条（API 提供 cores_usage 时渲染）
        this.updateCoreBars(cpu.cores_usage || []);

        // 内存模块（分段：used / buffers / cached）
        const mem = data.memory;
        if (mem && mem.total > 0) {
            this.updateText('memUsed', mem.used.toFixed(2));
            this.updateText('memTotal', mem.total.toFixed(2));

            const usedPct = Math.round((mem.used / mem.total) * 100);
            const buffPct = mem.buffers > 0 ? Math.round((mem.buffers / mem.total) * 100) : 0;
            const cachePct = mem.cached > 0 ? Math.round((mem.cached / mem.total) * 100) : 0;
            this.renderBar('memBar', [
                { cls: 'usr', count: usedPct },
                { cls: 'blu', count: buffPct },
                { cls: 'gry', count: cachePct }
            ]);
        }

        // Swap
        if (mem.swap_total > 0) {
            this.updateText('swapUsed', mem.swap_used.toFixed(2));
            this.updateText('swapTotal', mem.swap_total.toFixed(2));
            const swapPercent = Math.round((mem.swap_used / mem.swap_total) * 100);
            this.renderBar('swapBar', [{ cls: 'usr', count: swapPercent }]);
        }

        // GPU模块
        if (data.gpu) {
            const gpuSection = document.getElementById('gpuSection');
            if (gpuSection) gpuSection.style.display = 'block';

            this.updateElement('gpuModel', this.escapeHtml(data.gpu.model));

            if (data.gpu.type === 'nvidia') {
                this.updateText('gpuTemp', Math.round(data.gpu.temperature));
                this.updateText('gpuUsage', data.gpu.gpu_usage);
                this.updateText('gpuMem', `${data.gpu.memory_used} / ${data.gpu.memory_total}`);
                this.renderBar('gpuBar', [{ cls: 'usr', count: data.gpu.gpu_usage }]);
                this.renderBar('gpuMemBar', [{ cls: 'blu', count: data.gpu.memory_usage }]);
            } else {
                this.updateText('gpuTemp', '-');
                this.updateText('gpuUsage', '-');
                this.updateText('gpuMem', '-');
            }
        }

        // 磁盘模块
        if (data.disks && data.disks.length > 0) {
            const disk = data.disks[0];
            this.updateText('diskMount', disk.mount);
            this.updateText('diskUsage', `${disk.used} / ${disk.size}`);
            this.renderBar('diskBar', [{ cls: 'usr', count: disk.use_percent || 0 }]);
        }
    }

    updateText(id, value) {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    }

    updateElement(id, value) {
        const el = document.getElementById(id);
        if (el) el.innerHTML = value;
    }

    /**
     * 渲染方块进度条
     * @param {string} barId DOM id
     * @param {Array<{cls: string, count: number}>} segments 色段（cls: usr/sys/blu/yel/gry/空）
     * @param {number} total 总方块数
     */
    renderBar(barId, segments, total = 60) {
        const bar = document.getElementById(barId);
        if (!bar) return;

        const counts = segments.map(s => Math.max(0, Math.round(s.count)));
        let used = counts.reduce((a, b) => a + b, 0);

        // 超出总块数时按比例缩放
        if (used > total) {
            const scale = total / used;
            for (let i = 0; i < counts.length; i++) {
                counts[i] = Math.max(0, Math.round(counts[i] * scale));
            }
            used = counts.reduce((a, b) => a + b, 0);
        }

        let html = '';
        segments.forEach((seg, i) => {
            if (counts[i] > 0) {
                html += `<i class="element ${seg.cls}"></i>`.repeat(counts[i]);
            }
        });
        html += `<i class="element"></i>`.repeat(Math.max(0, total - used));

        bar.innerHTML = html;
    }

    updateCoreBars(cores) {
        const container = document.getElementById('coreBars');
        if (!container) return;

        if (cores.length > 0) {
            // 动态生成每核方块条
            let html = '';
            cores.forEach((usage, index) => {
                html += `
                    <div class="space-sm"></div>
                    <div class="left"><span class="type">CORE${index + 1}</span></div>
                    <div class="clear"></div>
                    <div class="bar" id="coreBar${index}"></div>
                `;
            });
            container.innerHTML = html;

            cores.forEach((usage, index) => {
                this.renderBar(`coreBar${index}`, [{ cls: 'usr', count: usage }]);
            });
        } else {
            // API 无每核数据时清空占位
            container.innerHTML = '';
        }
    }

    formatUptime(str) {
        return String(str || '')
            .replace('up ', '')
            .replace(/(\d+) week/g, '$1W')
            .replace(/(\d+) day/g, '$1D')
            .replace(/(\d+) hour/g, '$1H')
            .replace(/(\d+) minute/g, '$1M');
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
