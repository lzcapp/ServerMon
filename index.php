<?php
/**
 * ServerMon - 服务器监控页面
 * 使用 AJAX 无刷新获取数据
 */
?>
<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ServerMon</title>
    
    <!-- Favicons -->
    <link rel="apple-touch-icon" sizes="180x180" href="/icons/apple-touch-icon.png?v=1719392798">
    <link rel="icon" type="image/png" sizes="32x32" href="/icons/favicon-32x32.png?v=1719392798">
    <link rel="icon" type="image/png" sizes="16x16" href="/icons/favicon-16x16.png?v=1719392798">
    <meta name="theme-color" content="#1a1a2e">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    
    <!-- Styles -->
    <link href="style.css" rel="stylesheet">
</head>

<body>
    <!-- 顶部导航 -->
    <header class="header">
        <div class="header-content">
            <div class="logo">
                <h1 id="hostname">Loading...</h1>
                <span class="subtitle" id="os"></span>
            </div>
            
            <div class="header-controls">
                <div class="refresh-interval">
                    <span class="label">Refresh:</span>
                    <button class="interval-btn" data-interval="1">1s</button>
                    <button class="interval-btn" data-interval="3">3s</button>
                    <button class="interval-btn" data-interval="5">5s</button>
                    <button class="interval-btn" data-interval="10">10s</button>
                    <button class="interval-btn" data-interval="30">30s</button>
                </div>
                
                <button id="refreshBtn" class="icon-btn" title="Refresh Now">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M23 4v6h-6M1 20v-6h6"/>
                        <path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/>
                    </svg>
                </button>
                
                <button id="themeToggle" class="icon-btn" title="Toggle Theme">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="5"/>
                        <line x1="12" y1="1" x2="12" y2="3"/>
                        <line x1="12" y1="21" x2="12" y2="23"/>
                        <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/>
                        <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
                        <line x1="1" y1="12" x2="3" y2="12"/>
                        <line x1="21" y1="12" x2="23" y2="12"/>
                        <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/>
                        <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
                    </svg>
                </button>
            </div>
        </div>
        
        <div class="header-meta">
            <span class="meta-item">
                <span class="meta-label">Uptime:</span>
                <span id="uptime">-</span>
            </span>
            <span class="meta-item">
                <span class="meta-label">Architecture:</span>
                <span id="arch">-</span>
            </span>
            <span class="meta-item">
                <span class="meta-label">Update Time:</span>
                <span id="updateTime">-</span>
            </span>
        </div>
    </header>

    <!-- 主内容 -->
    <main class="main-content">
        <!-- 系统概览 -->
        <section class="card">
            <div class="card-header">
                <h2>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
                        <line x1="8" y1="21" x2="16" y2="21"/>
                        <line x1="12" y1="17" x2="12" y2="21"/>
                    </svg>
                    CPU
                </h2>
                <div class="card-actions">
                    <span id="cpuLoad" class="load-avg">-</span>
                </div>
            </div>
            <div class="card-body">
                <div class="stat-row">
                    <span id="cpuModel" class="stat-value">-</span>
                    <span id="cpuCores" class="stat-secondary">-</span>
                </div>
                <div class="usage-display">
                    <span id="cpuUsage" class="usage-percent">0%</span>
                    <div class="usage-bar">
                        <div id="cpuBar" class="bar-fill"></div>
                    </div>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Temperature</span>
                    <span id="cpuTemp" class="stat-value">-</span>
                </div>
            </div>
        </section>

        <!-- 内存 -->
        <section class="card">
            <div class="card-header">
                <h2>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M6 19v-14a2 2 0 012-2h8a2 2 0 012 2v14a2 2 0 01-2 2H8a2 2 0 01-2-2z"/>
                        <line x1="6" y1="9" x2="18" y2="9"/>
                    </svg>
                    MEM
                </h2>
            </div>
            <div class="card-body">
                <div class="usage-display">
                    <span class="usage-text">
                        <span id="memUsed" class="usage-percent">0 GB</span>
                        <span id="memTotal" class="usage-total">/ 0 GB</span>
                    </span>
                    <div class="usage-bar">
                        <div id="memBar" class="bar-fill"></div>
                    </div>
                </div>
            </div>
            
            <div class="card-divider"></div>
            
            <div class="card-body">
                <div class="card-header">
                    <h3>SWAP</h3>
                </div>
                <div class="usage-display">
                    <span class="usage-text">
                        <span id="swapUsed" class="usage-percent">0 GB</span>
                        <span id="swapTotal" class="usage-total">/ 0 GB</span>
                    </span>
                    <div class="usage-bar">
                        <div id="swapBar" class="bar-fill"></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- GPU (条件显示) -->
        <section id="gpuCard" class="card" style="display: none;">
            <div class="card-header">
                <h2>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="4" y="4" width="16" height="16" rx="2" ry="2"/>
                        <rect x="9" y="9" width="6" height="6"/>
                        <line x1="9" y1="1" x2="9" y2="4"/>
                        <line x1="15" y1="1" x2="15" y2="4"/>
                        <line x1="9" y1="20" x2="9" y2="23"/>
                        <line x1="15" y1="20" x2="15" y2="23"/>
                        <line x1="20" y1="9" x2="23" y2="9"/>
                        <line x1="20" y1="14" x2="23" y2="14"/>
                        <line x1="1" y1="9" x2="4" y2="9"/>
                        <line x1="1" y1="14" x2="4" y2="14"/>
                    </svg>
                    GPU
                </h2>
                <span id="gpuModel" class="gpu-name">-</span>
            </div>
            <div class="card-body">
                <div class="gpu-grid">
                    <div class="gpu-stat">
                        <span class="stat-label">Temperature</span>
                        <span id="gpuTemp" class="stat-value">-</span>
                    </div>
                    <div class="gpu-stat">
                        <span class="stat-label">VRAM</span>
                        <span id="gpuMem" class="stat-value">-</span>
                    </div>
                    <div class="gpu-stat">
                        <span class="stat-label">Usage</span>
                        <span id="gpuUsage" class="stat-value">-</span>
                    </div>
                </div>
                <div class="usage-bar">
                    <div id="gpuBar" class="bar-fill"></div>
                </div>
                <div class="stat-row">
                    <span class="stat-label">VRAM Usage</span>
                    <div class="usage-bar small">
                        <div id="gpuMemBar" class="bar-fill"></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 磁盘 -->
        <section class="card">
            <div class="card-header">
                <h2>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <ellipse cx="12" cy="5" rx="9" ry="3"/>
                        <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/>
                        <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>
                    </svg>
                    DISK
                </h2>
                <span id="diskMount" class="disk-mount">/</span>
            </div>
            <div class="card-body">
                <div class="usage-display">
                    <span id="diskUsage" class="usage-text">-</span>
                    <div class="usage-bar">
                        <div id="diskBar" class="bar-fill"></div>
                    </div>
                </div>
            </div>
        </section>

        </main>

    <!-- Scripts -->
    <script src="js/monitor.js"></script>
</body>

</html>
