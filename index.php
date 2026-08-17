<?php
/**
 * ServerMon - 服务器监控页面
 * 使用 AJAX 无刷新获取数据
 * 视觉风格：终端方块条（classic terminal style）
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
    <meta name="theme-color" content="#222222">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">

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

        <!-- CPU -->
        <div class="module">
            <div class="left" id="cpuModelInfo">-</div>
            <div class="right" id="cpuStats">-</div>
            <div class="clear"></div>

            <div class="bar" id="cpuBar"><i class="element"></i></div>

            <div id="coreBars"></div>
        </div>

        <!-- 内存 -->
        <div class="space"></div>
        <div class="module">
            <div class="left"><span class="type">MEM</span></div>
            <div class="right"><span id="memUsed" class="value">0.00</span><span class="slash">/</span><span id="memTotal">0.00</span><span class="unit">GB</span></div>
            <div class="clear"></div>

            <div class="bar" id="memBar"><i class="element"></i></div>
        </div>

        <!-- Swap -->
        <div class="space-sm"></div>
        <div class="module">
            <div class="left"><span class="type">SWAP</span></div>
            <div class="right"><span id="swapUsed" class="value">0.00</span><span class="slash">/</span><span id="swapTotal">0.00</span><span class="unit">GB</span></div>
            <div class="clear"></div>

            <div class="bar" id="swapBar"><i class="element"></i></div>
        </div>

        <!-- GPU (条件显示) -->
        <div id="gpuSection" style="display:none;">
            <div class="space"></div>
            <div class="module">
                <div class="left" id="gpuModel">-</div>
                <div class="right"><span id="gpuTemp">-</span><span class="unit">°C</span></div>
                <div class="clear"></div>

                <div class="left"><span class="type">GPU</span></div>
                <div class="right"><span id="gpuUsage">0</span><span class="unit">%</span></div>
                <div class="clear"></div>
                <div class="bar" id="gpuBar"><i class="element"></i></div>

                <div class="space-sm"></div>

                <div class="left"><span class="type">VRAM</span></div>
                <div class="right"><span id="gpuMem">0 / 0</span><span class="unit">MB</span></div>
                <div class="clear"></div>
                <div class="bar" id="gpuMemBar"><i class="element"></i></div>
            </div>
        </div>

        <!-- 磁盘 -->
        <div class="space"></div>
        <div class="module">
            <div class="left"><span class="type">DISK</span></div>
            <div class="right"><span id="diskMount">/</span></div>
            <div class="clear"></div>

            <div class="left" id="diskUsage">-</div>
            <div class="clear"></div>
            <div class="bar" id="diskBar"><i class="element"></i></div>
        </div>

    </main>

    <!-- Scripts -->
    <script src="js/monitor.js"></script>
</body>

</html>
