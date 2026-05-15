<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['user_name'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Analytics Pro - IoT Monitoring</title>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom@2.0.1/dist/chartjs-plugin-zoom.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

    <style>
        :root {
            --primary: #3B82F6;
            --success: #10B981;
            --warning: #F59E0B;
            --danger: #EF4444;
            --purple: #8B5CF6;
        }

        * { scroll-behavior: smooth; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f5f7fa;
            background-attachment: fixed;
            background-image: 
                radial-gradient(ellipse at 20% 50%, rgba(59, 130, 246, 0.03) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 20%, rgba(139, 92, 246, 0.03) 0%, transparent 50%),
                radial-gradient(ellipse at 50% 80%, rgba(59, 130, 246, 0.02) 0%, transparent 50%);
        }

        .status-online { color: #10B981; text-shadow: 0 0 10px rgba(16, 185, 129, 0.3); }
        .status-offline { color: #EF4444; text-shadow: 0 0 10px rgba(239, 68, 68, 0.3); }

        .chart-container {
            position: relative; width: 100%; height: 200px;
            background: rgba(59, 130, 246, 0.03); border-radius: 12px; padding: 10px;
            border: 1px solid rgba(0, 0, 0, 0.04);
        }

        .pdf-loading {
            display: none; position: fixed; top: 50%; left: 50%;
            transform: translate(-50%, -50%); background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px); padding: 30px; border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.25); z-index: 9999;
        }

        .glass-card {
            background: #ffffff; border: 1px solid rgba(0, 0, 0, 0.06);
            transition: all 0.3s; box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .glass-card:hover { transform: translateY(-2px); box-shadow: 0 4px 6px rgba(0,0,0,0.05); }

        .gradient-text {
            background: linear-gradient(135deg, #3B82F6 0%, #8B5CF6 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }

        .float-animation { animation: float 3s ease-in-out infinite; }
        @keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-10px)} }

        .metric-value {
            font-size: 2rem; font-weight: 800;
            background: linear-gradient(135deg, #3B82F6 0%, #8B5CF6 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }

        canvas { filter: drop-shadow(0 2px 3px rgba(0,0,0,0.05)); }
        button { transition: all 0.25s; }
        button:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }

        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: rgba(0,0,0,0.03); border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: linear-gradient(135deg, #3B82F6 0%, #8B5CF6 100%); border-radius: 10px; }

        /* Alert curve legend styles */
        .alert-legend-item {
            display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600;
        }
        .alert-legend-color {
            width: 30px; height: 4px; border-radius: 2px;
        }
    </style>
</head>
<body class="min-h-screen">

    <!-- PDF Loading Overlay -->
    <div id="pdfLoading" class="pdf-loading">
        <div class="text-center">
            <div class="relative mb-6">
                <div class="animate-spin rounded-full h-16 w-16 border-4 border-purple-200 mx-auto"></div>
                <div class="animate-spin rounded-full h-16 w-16 border-t-4 border-purple-600 mx-auto absolute top-0 left-1/2 transform -translate-x-1/2"></div>
            </div>
            <p class="text-gray-800 font-bold text-lg">Generating PDF Report...</p>
            <p class="text-gray-500 text-sm mt-2">This may take a few seconds</p>
        </div>
    </div>

    <div class="container mx-auto p-6 max-w-7xl">

        <!-- TOP BAR -->
        <div class="glass-card rounded-2xl px-6 py-4 mb-6">
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div class="flex items-center gap-6">
                    <a href="dashboard.php" class="flex items-center gap-2 bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 text-white px-6 py-2.5 rounded-full font-semibold transition shadow-lg">
                        <span>←</span> Dashboard
                    </a>
                    <div class="flex items-center gap-3">
                        <span class="text-2xl float-animation">⚡</span>
                        <h1 class="text-3xl font-bold gradient-text">Smart Analytics Pro</h1>
                    </div>
                </div>
                <div class="flex items-center gap-5">
                    <button onclick="generatePDFReport()" class="bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 text-white px-6 py-2.5 rounded-lg shadow-lg font-semibold transition">
                        📄 Generate PDF Report
                    </button>
                    <div class="flex items-center gap-2 text-sm font-semibold">
                        <span class="relative flex h-3 w-3">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                        </span>
                        <span id="liveStatusText" class="text-green-700">Real-time</span>
                    </div>
                    <div class="flex items-center gap-3 bg-gray-100 px-4 py-2 rounded-full">
                        <span class="text-sm font-bold"><?php echo htmlspecialchars($username); ?></span>
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-600 to-purple-600 text-white flex items-center justify-center font-bold">
                            <?php echo strtoupper(substr($username,0,1)); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MAIN CARDS (3 columns) -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">

            <!-- DEVICE STATUS -->
            <div class="glass-card rounded-xl shadow-lg p-6">
                <div class="flex justify-between mb-4">
                    <h2 class="font-bold text-lg text-gray-800">Device Status</h2>
                    <span class="text-3xl float-animation">📡</span>
                </div>
                <div class="space-y-4">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center"><span>🔌</span></div>
                        <div><span class="text-sm text-gray-500">Status</span><p><span id="deviceStatus" class="font-bold text-lg status-online">Online</span></p></div>
                    </div>
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center"><span>⏱️</span></div>
                        <div><span class="text-sm text-gray-500">Last Activity</span><p><span id="lastSeen" class="font-mono text-sm font-bold">--:--:--</span></p></div>
                    </div>
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center"><span>🔄</span></div>
                        <div><span class="text-sm text-gray-500">Uptime</span><p><span id="uptime" class="font-mono text-sm font-bold">--</span></p></div>
                    </div>
                </div>
            </div>

            <!-- TEMPERATURE ANALYSIS -->
            <div class="glass-card rounded-xl shadow-lg p-6">
                <div class="flex justify-between mb-4">
                    <h2 class="font-bold text-lg text-gray-800">Temperature</h2>
                    <span id="tempIcon" class="text-3xl float-animation">🌡️</span>
                </div>
                <div class="mb-4">
                    <div class="flex items-center gap-2"><span class="text-sm text-gray-500">Trend:</span><span id="tempTrend" class="font-bold px-3 py-1 rounded-full text-sm bg-green-100 text-green-700">Stable</span></div>
                    <div class="mt-3"><span class="text-sm text-gray-500">Current:</span><p><span id="currentTemp" class="metric-value">--°C</span></p></div>
                </div>
                <div class="chart-container"><canvas id="tempTrendChart"></canvas></div>
            </div>

            <!-- NETWORK ANALYSIS -->
            <div class="glass-card rounded-xl shadow-lg p-6">
                <div class="flex justify-between mb-4">
                    <h2 class="font-bold text-lg text-gray-800">Network</h2>
                    <span id="signalIcon" class="text-3xl float-animation">📶</span>
                </div>
                <div class="mb-4">
                    <div class="flex items-center gap-2"><span class="text-sm text-gray-500">Signal:</span><span id="signalTrend" class="font-bold px-3 py-1 rounded-full text-sm bg-green-100 text-green-700">Excellent</span></div>
                    <div class="mt-3"><span class="text-sm text-gray-500">Strength:</span><p><span id="currentSignal" class="metric-value">--%</span></p></div>
                </div>
                <div class="chart-container"><canvas id="signalTrendChart"></canvas></div>
            </div>
        </div>

        <!-- AI SECTION -->
        <div class="glass-card rounded-xl shadow-lg p-6 mb-6">
            <div class="flex items-center justify-between mb-6">
                <div><h2 class="text-2xl font-bold gradient-text">Artificial Intelligence</h2><p class="text-sm text-gray-600">Predictive Analysis & Anomaly Detection</p></div>
                <div class="flex items-center gap-2"><span class="text-green-500">●</span><span id="aiStatus" class="text-sm font-bold text-green-600">Active</span></div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-purple-50 rounded-lg p-5 border border-purple-100">
                    <div class="flex items-center gap-2 mb-3"><span>💡</span><h3 class="font-bold text-purple-700">Insights</h3></div>
                    <div id="insightsContainer" class="space-y-2 text-sm"><p class="text-gray-500">Analyzing...</p></div>
                </div>
                <div class="bg-blue-50 rounded-lg p-5 border border-blue-100">
                    <div class="flex items-center gap-2 mb-3"><span>🔮</span><h3 class="font-bold text-blue-700">Predictions</h3></div>
                    <div id="predictionsContainer" class="space-y-2 text-sm"><p class="text-gray-500">Computing...</p></div>
                </div>
                <div class="bg-green-50 rounded-lg p-5 border border-green-100">
                    <div class="flex items-center gap-2 mb-3"><span>🎯</span><h3 class="font-bold text-green-700">Recommendations</h3></div>
                    <div id="recommendationsContainer" class="space-y-2 text-sm"><p class="text-gray-500">Loading...</p></div>
                </div>
            </div>
            <div class="mt-6 grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-lg p-4 text-center shadow border"><span class="text-sm text-gray-500">AI Score</span><p><span id="aiScore" class="text-2xl font-bold text-purple-600">--</span></p></div>
                <div class="bg-white rounded-lg p-4 text-center shadow border"><span class="text-sm text-gray-500">Health</span><p><span id="aiHealth" class="text-2xl font-bold text-green-600">--</span></p></div>
                <div class="bg-white rounded-lg p-4 text-center shadow border"><span class="text-sm text-gray-500">Anomalies</span><p><span id="anomalyText" class="text-2xl font-bold text-gray-600">None</span></p></div>
                <div class="bg-white rounded-lg p-4 text-center shadow border"><span class="text-sm text-gray-500">Data Points</span><p><span id="dataPoints" class="text-2xl font-bold text-blue-600">0</span></p></div>
            </div>
        </div>

        <!-- MAIN CHARTS (2 columns) -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <!-- HISTORY CHART + PREDICTION -->
            <div class="glass-card rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-lg gradient-text">📊 History Chart</h3>
                    <div class="flex space-x-2 bg-gray-100 rounded-lg p-1">
                        <button onclick="switchHistoryView('24h')" class="px-4 py-1.5 text-sm rounded-md bg-blue-600 text-white shadow-md" id="btn24h">24h</button>
                        <button onclick="switchHistoryView('7d')" class="px-4 py-1.5 text-sm rounded-md hover:bg-white" id="btn7d">7d</button>
                        <button onclick="switchHistoryView('30d')" class="px-4 py-1.5 text-sm rounded-md hover:bg-white" id="btn30d">30d</button>
                    </div>
                </div>
                <div class="chart-container" style="height:300px;"><canvas id="historyChart"></canvas></div>
                <div class="mt-4 grid grid-cols-3 gap-4">
                    <div class="bg-blue-50 rounded-lg p-3 text-center border"><span class="text-xs">Min</span><p><span id="historyMin" class="font-bold text-blue-700">--</span></p></div>
                    <div class="bg-purple-50 rounded-lg p-3 text-center border"><span class="text-xs">Max</span><p><span id="historyMax" class="font-bold text-purple-700">--</span></p></div>
                    <div class="bg-green-50 rounded-lg p-3 text-center border"><span class="text-xs">Avg</span><p><span id="historyAvg" class="font-bold text-green-700">--</span></p></div>
                </div>
                <div class="mt-6">
                    <div class="flex items-center gap-2 mb-3"><span>🔮</span><h4 class="font-bold text-purple-700">24h Prediction</h4></div>
                    <div class="chart-container" style="height:220px;"><canvas id="predictChart"></canvas></div>
                </div>
            </div>

            <!-- ALERT CHART - 3 CURVES -->
            <div class="glass-card rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-lg gradient-text">⚠️ Alert Curves</h3>
                </div>

                <!-- LEGEND FOR 3 CURVES -->
                <div class="flex flex-wrap gap-4 mb-4 p-3 bg-gray-50 rounded-lg">
                    <div class="alert-legend-item">
                        <div class="alert-legend-color" style="background:#EF4444;"></div>
                        <span>🌡️ Temperature</span>
                    </div>
                    <div class="alert-legend-item">
                        <div class="alert-legend-color" style="background:#F59E0B;"></div>
                        <span>📶 Signal</span>
                    </div>
                    <div class="alert-legend-item">
                        <div class="alert-legend-color" style="background:#3B82F6;"></div>
                        <span>🔌 Connection</span>
                    </div>
                </div>

                <!-- 3 CURVES CHART -->
                <div class="chart-container" style="height:340px;">
                    <canvas id="alertChart"></canvas>
                </div>

                <!-- COUNTERS BY TYPE -->
                <div class="mt-4 grid grid-cols-3 gap-3">
                    <div class="bg-red-50 rounded-lg p-3 text-center border border-red-200">
                        <span class="text-xs text-gray-600">🌡️ Temp Alerts</span>
                        <p><span id="tempAlerts" class="font-bold text-2xl text-red-600">0</span></p>
                    </div>
                    <div class="bg-orange-50 rounded-lg p-3 text-center border border-orange-200">
                        <span class="text-xs text-gray-600">📶 Signal Alerts</span>
                        <p><span id="signalAlerts" class="font-bold text-2xl text-orange-600">0</span></p>
                    </div>
                    <div class="bg-blue-50 rounded-lg p-3 text-center border border-blue-200">
                        <span class="text-xs text-gray-600">🔌 Conn Alerts</span>
                        <p><span id="connectionAlerts" class="font-bold text-2xl text-blue-600">0</span></p>
                    </div>
                </div>

                <!-- TOTAL -->
                <div class="mt-4 bg-gradient-to-r from-red-50 to-orange-50 rounded-lg p-4 border border-red-100">
                    <p class="text-sm"><span class="font-bold">Total Alerts:</span><span id="todayAlerts" class="font-bold text-2xl text-red-600 ml-2">0</span></p>
                </div>
            </div>
        </div>
    </div>

    <script>
        const CONFIG = {
            UPDATE_INTERVAL: 5000,
            OFFLINE_THRESHOLD: 30000,
            TEMP_CRITICAL: 28,
            TEMP_WARNING: 24,
            SIGNAL_CRITICAL: 30,
            SIGNAL_WARNING: 50,
            MAX_DATA_POINTS: 50
        };

        const state = {
            charts: {},
            lastUpdate: Date.now(),
            currentPeriod: '24h',
            temperatureHistory: [],
            signalHistory: [],
            anomalyCount: 0
        };

        function safeNum(v) { const n = parseFloat(v); return isNaN(n) ? 0 : n; }

        // ============================================
        // INITIALIZE ALL CHARTS
        // ============================================
        function initCharts() {
            // Mini temperature chart
            state.charts.temp = new Chart(document.getElementById('tempTrendChart'), {
                type: 'line',
                data: { labels: [], datasets: [{ data: [], borderColor: '#f97316', backgroundColor: 'rgba(249,115,22,0.1)', fill: true, tension: 0.4, borderWidth: 2.5, pointRadius: 3, pointBackgroundColor: '#f97316' }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: false, grid: { color: 'rgba(0,0,0,0.05)' } }, x: { grid: { display: false } } } }
            });

            // Mini signal chart
            state.charts.signal = new Chart(document.getElementById('signalTrendChart'), {
                type: 'line',
                data: { labels: [], datasets: [{ data: [], borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,0.1)', fill: true, tension: 0.4, borderWidth: 2.5, pointRadius: 3, pointBackgroundColor: '#3b82f6' }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: false, grid: { color: 'rgba(0,0,0,0.05)' } }, x: { grid: { display: false } } } }
            });

            // History chart (dual axis)
            state.charts.history = new Chart(document.getElementById('historyChart'), {
                type: 'line',
                data: { labels: [], datasets: [
                    { label: 'Temperature °C', data: [], borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,0.1)', yAxisID: 'y', tension: 0.4, fill: true, borderWidth: 3, pointRadius: 3 },
                    { label: 'Signal %', data: [], borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,0.1)', yAxisID: 'y1', tension: 0.4, fill: true, borderWidth: 3, pointRadius: 3 }
                ]},
                options: {
                    responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                    plugins: { zoom: { zoom: { wheel: { enabled: true }, pinch: { enabled: true }, mode: 'x' }, pan: { enabled: true, mode: 'x' } } },
                    scales: {
                        y: { position: 'left', title: { display: true, text: 'Temperature (°C)', color: '#F59E0B' }, grid: { color: 'rgba(0,0,0,0.05)' } },
                        y1: { position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Signal (%)', color: '#3B82F6' } },
                        x: { grid: { display: false } }
                    }
                }
            });

            // ============================================
            // ALERT CHART - 3 DISTINCT CURVES
            // Curve 1: Temperature Alerts (Red)
            // Curve 2: Signal Alerts (Orange)
            // Curve 3: Connection Alerts (Blue)
            // ============================================
            state.charts.alert = new Chart(document.getElementById('alertChart'), {
                type: 'line',
                data: {
                    labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    datasets: [
                        {
                            label: '🌡️ Temperature Alerts',
                            data: [3, 5, 2, 7, 4, 6, 8],
                            borderColor: '#EF4444',
                            backgroundColor: 'rgba(239, 68, 68, 0.08)',
                            fill: true,
                            tension: 0.4,
                            borderWidth: 3,
                            pointRadius: 5,
                            pointBackgroundColor: '#EF4444',
                            pointBorderColor: '#FFFFFF',
                            pointBorderWidth: 2,
                            pointHoverRadius: 7
                        },
                        {
                            label: '📶 Signal Alerts',
                            data: [1, 3, 4, 2, 5, 3, 4],
                            borderColor: '#F59E0B',
                            backgroundColor: 'rgba(245, 158, 11, 0.08)',
                            fill: true,
                            tension: 0.4,
                            borderWidth: 3,
                            pointRadius: 5,
                            pointBackgroundColor: '#F59E0B',
                            pointBorderColor: '#FFFFFF',
                            pointBorderWidth: 2,
                            pointHoverRadius: 7
                        },
                        {
                            label: '🔌 Connection Alerts',
                            data: [2, 1, 3, 1, 2, 4, 3],
                            borderColor: '#3B82F6',
                            backgroundColor: 'rgba(59, 130, 246, 0.08)',
                            fill: true,
                            tension: 0.4,
                            borderWidth: 3,
                            pointRadius: 5,
                            pointBackgroundColor: '#3B82F6',
                            pointBorderColor: '#FFFFFF',
                            pointBorderWidth: 2,
                            pointHoverRadius: 7
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    animation: { duration: 1000, easing: 'easeInOutQuart' },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                pointStyleWidth: 12,
                                padding: 15,
                                font: { size: 12, weight: 'bold' },
                                generateLabels: function(chart) {
                                    const datasets = chart.data.datasets;
                                    return datasets.map((ds, i) => ({
                                        text: ds.label,
                                        fillStyle: ds.borderColor,
                                        strokeStyle: ds.borderColor,
                                        lineWidth: 3,
                                        hidden: false,
                                        index: i,
                                        pointStyle: 'circle',
                                        pointStyleWidth: 10
                                    }));
                                }
                            }
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            backgroundColor: 'rgba(0,0,0,0.85)',
                            padding: 12,
                            cornerRadius: 8,
                            callbacks: {
                                label: function(ctx) {
                                    return ctx.dataset.label + ': ' + ctx.parsed.y + ' alerts';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: { display: true, text: 'Number of Alerts', font: { weight: 'bold', size: 13 } },
                            grid: { color: 'rgba(0,0,0,0.05)', drawBorder: false },
                            ticks: { stepSize: 1, font: { size: 11 } }
                        },
                        x: {
                            title: { display: true, text: 'Days of the Week', font: { weight: 'bold', size: 13 } },
                            grid: { display: false },
                            ticks: { font: { size: 11 } }
                        }
                    }
                }
            });

            // Prediction chart
            state.charts.predict = new Chart(document.getElementById('predictChart'), {
                type: 'line',
                data: { labels: [], datasets: [{ label: '24h Prediction', data: [], borderColor: '#8b5cf6', backgroundColor: 'rgba(139,92,246,0.1)', fill: true, tension: 0.4, borderWidth: 3, pointRadius: 4, borderDash: [8,4] }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: true, position: 'top' } }, scales: { y: { beginAtZero: false, grid: { color: 'rgba(0,0,0,0.05)' } }, x: { grid: { display: false } } } }
            });

            // Initialize alert data
            updateAlertChartData();
        }

        // ============================================
        // UPDATE ALERT DATA (3 CURVES)
        // ============================================
        function updateAlertChartData() {
            // Generate random data for 7 days
            const tempData = [];
            const signalData = [];
            const connData = [];
            
            for (let i = 0; i < 7; i++) {
                tempData.push(Math.floor(Math.random() * 10) + 1);   // 1-10 temperature alerts
                signalData.push(Math.floor(Math.random() * 7) + 1);  // 1-7 signal alerts
                connData.push(Math.floor(Math.random() * 5) + 1);    // 1-5 connection alerts
            }

            // Update chart
            state.charts.alert.data.datasets[0].data = tempData;
            state.charts.alert.data.datasets[1].data = signalData;
            state.charts.alert.data.datasets[2].data = connData;
            state.charts.alert.update('active');

            // Update counters
            const totalTemp = tempData.reduce((a, b) => a + b, 0);
            const totalSignal = signalData.reduce((a, b) => a + b, 0);
            const totalConn = connData.reduce((a, b) => a + b, 0);

            document.getElementById('tempAlerts').textContent = totalTemp;
            document.getElementById('signalAlerts').textContent = totalSignal;
            document.getElementById('connectionAlerts').textContent = totalConn;
            document.getElementById('todayAlerts').textContent = totalTemp + totalSignal + totalConn;

            calculateAIScore([totalTemp, totalSignal, totalConn]);
        }

        function calculateAIScore(arr) {
            const total = arr.reduce((a, b) => a + b, 0);
            const score = Math.max(0, Math.min(100, 100 - total * 2));
            document.getElementById('aiScore').textContent = score + '%';
            let status = 'Excellent', cls = 'text-green-600';
            if (score < 50) { status = 'Critical'; cls = 'text-red-600'; }
            else if (score < 70) { status = 'Warning'; cls = 'text-orange-600'; }
            else if (score < 90) { status = 'Good'; cls = 'text-blue-600'; }
            document.getElementById('aiHealth').textContent = status;
            document.getElementById('aiHealth').className = `text-2xl font-bold ${cls}`;
        }

        function pushMini(chart, value) {
            const now = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
            chart.data.labels.push(now);
            chart.data.datasets[0].data.push(value);
            if (chart.data.labels.length > CONFIG.MAX_DATA_POINTS) {
                chart.data.labels.shift();
                chart.data.datasets[0].data.shift();
            }
            chart.update('none');
        }

        async function switchHistoryView(period) {
            state.currentPeriod = period;
            document.querySelectorAll('[id^="btn"]').forEach(b => { b.classList.remove('bg-blue-600','text-white','shadow-md'); });
            document.getElementById(`btn${period}`).classList.add('bg-blue-600','text-white','shadow-md');
            
            try {
                const r = await fetch(`get_history.php?period=${period}&user_id=<?= $user_id ?>`);
                const j = await r.json();
                if (!j.success || !j.history) return;
                
                const rows = j.history.reverse();
                const labels = rows.map(x => {
                    const d = new Date(x.timestamp);
                    if (period === '7d') return d.toLocaleDateString('en-US', { weekday: 'short', day: 'numeric' });
                    if (period === '30d') return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                    return d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                });
                
                const temp = rows.map(x => safeNum(x.temperature));
                const signal = rows.map(x => safeNum(x.signal_strength));
                
                state.charts.history.data.labels = labels;
                state.charts.history.data.datasets[0].data = temp;
                state.charts.history.data.datasets[1].data = signal;
                state.charts.history.update();
                
                if (temp.length) {
                    document.getElementById('historyMin').textContent = Math.min(...temp).toFixed(1) + '°C';
                    document.getElementById('historyMax').textContent = Math.max(...temp).toFixed(1) + '°C';
                    document.getElementById('historyAvg').textContent = (temp.reduce((a,b)=>a+b,0)/temp.length).toFixed(1) + '°C';
                    document.getElementById('dataPoints').textContent = temp.length;
                }
                generatePrediction(temp);
            } catch (e) { console.error("History error:", e); }
        }

        function generatePrediction(history) {
            if (history.length < 5) return;
            const recent = history.slice(-10);
            const avg = recent.reduce((a,b)=>a+b,0)/recent.length;
            const trend = recent[recent.length-1] - recent[0];
            const labels = [], values = [];
            for (let i=1; i<=24; i++) {
                labels.push(`+${i}h`);
                values.push(parseFloat((avg + (trend/recent.length)*i + (Math.random()*0.5-0.25)).toFixed(2)));
            }
            state.charts.predict.data.labels = labels;
            state.charts.predict.data.datasets[0].data = values;
            state.charts.predict.update();
        }

        function detectAnomaly(temp, signal) {
            let anomaly = 'None', cls = 'text-green-600';
            if (temp > 30 || signal < 20) { anomaly = 'Critical'; cls = 'text-red-600'; }
            else if (temp > 26 || signal < 40) { anomaly = 'Warning'; cls = 'text-orange-600'; }
            document.getElementById('anomalyText').textContent = anomaly;
            document.getElementById('anomalyText').className = `text-2xl font-bold ${cls}`;
        }

        function analyzeTemp(temp) {
            document.getElementById('currentTemp').textContent = temp.toFixed(1) + '°C';
            let s = 'Stable', c = 'bg-green-100 text-green-700', icon = '🌡️';
            if (temp > CONFIG.TEMP_CRITICAL) { s = 'Critical'; c = 'bg-red-100 text-red-700'; icon = '🔥'; }
            else if (temp > CONFIG.TEMP_WARNING) { s = 'High'; c = 'bg-orange-100 text-orange-700'; }
            document.getElementById('tempTrend').textContent = s;
            document.getElementById('tempTrend').className = `font-bold px-3 py-1 rounded-full text-sm ${c}`;
            document.getElementById('tempIcon').textContent = icon;
            pushMini(state.charts.temp, temp);
        }

        function analyzeSignal(signal) {
            document.getElementById('currentSignal').textContent = signal + '%';
            let s = 'Excellent', c = 'bg-green-100 text-green-700';
            if (signal < CONFIG.SIGNAL_CRITICAL) { s = 'Critical'; c = 'bg-red-100 text-red-700'; }
            else if (signal < CONFIG.SIGNAL_WARNING) { s = 'Weak'; c = 'bg-orange-100 text-orange-700'; }
            document.getElementById('signalTrend').textContent = s;
            document.getElementById('signalTrend').className = `font-bold px-3 py-1 rounded-full text-sm ${c}`;
            pushMini(state.charts.signal, signal);
        }

        function updateDeviceStatus() {
            const online = (Date.now() - state.lastUpdate) < CONFIG.OFFLINE_THRESHOLD;
            const el = document.getElementById('deviceStatus');
            if (online) { el.textContent = 'Online'; el.className = 'font-bold text-lg status-online'; }
            else { el.textContent = 'Offline'; el.className = 'font-bold text-lg status-offline'; }
        }

        function updateUptime() {
            const sec = Math.floor((Date.now() - state.lastUpdate)/1000);
            const m = Math.floor(sec/60), h = Math.floor(m/60);
            document.getElementById('uptime').textContent = h>0 ? `${h}h ${m%60}m` : m>0 ? `${m}m ${sec%60}s` : `${sec}s`;
        }

        async function loadAnalytics() {
            try {
                const r = await fetch(`get_latest_data.php?user_id=<?= $user_id ?>`);
                const j = await r.json();
                if (!j.success || !j.data) return;
                
                state.lastUpdate = Date.now();
                const temp = safeNum(j.data.temperature);
                const signal = safeNum(j.data.signal_strength);
                
                document.getElementById('lastSeen').textContent = new Date().toLocaleTimeString('en-US', { hour:'2-digit', minute:'2-digit', second:'2-digit' });
                analyzeTemp(temp); analyzeSignal(signal); detectAnomaly(temp, signal);
                
                document.getElementById('insightsContainer').innerHTML = `<p><strong>Temp:</strong> ${temp.toFixed(1)}°C ${temp>CONFIG.TEMP_WARNING?'⚠️':'✅'}</p><p><strong>Signal:</strong> ${signal}% ${signal<CONFIG.SIGNAL_WARNING?'⚠️':'✅'}</p>`;
                document.getElementById('predictionsContainer').innerHTML = `<p>${temp>27?'⚠️ Temperature rising trend':'✅ Temperature stable'}</p><p>${signal<40?'⚠️ Signal degradation possible':'✅ Signal stable'}</p>`;
                document.getElementById('recommendationsContainer').innerHTML = `<p>${temp>CONFIG.TEMP_WARNING?'🌡️ Monitor temperature':'✅ Temperature normal'}</p><p>${signal<CONFIG.SIGNAL_WARNING?'📶 Check network':'✅ Network optimal'}</p>`;
                
                updateAlertChartData();
            } catch (e) { console.error("Error:", e); }
        }

        async function generatePDFReport() {
            const ld = document.getElementById('pdfLoading');
            if (ld) ld.style.display = 'block';
            try {
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF('p', 'mm', 'a4');
                const W=210, H=297;
                const username = "<?= htmlspecialchars($username) ?>";
                const ct = document.getElementById('currentTemp')?.textContent || '--';
                const cs = document.getElementById('currentSignal')?.textContent || '--';
                const ai = document.getElementById('aiScore')?.textContent || '--';
                const an = document.getElementById('anomalyText')?.textContent || '--';
                const ds = document.getElementById('deviceStatus')?.textContent || '--';
                const sn = parseFloat(ai) || 0;
                const tn = parseFloat(ct) || 0;
                const sgn = parseFloat(cs) || 0;

                const addHeader = (t) => { doc.setFillColor(30,64,175); doc.rect(0,0,W,18,'F'); doc.setTextColor(255,255,255); doc.setFontSize(15); doc.text(t,15,12); };
                const addFooter = () => { for(let i=1;i<=doc.internal.getNumberOfPages();i++){ doc.setPage(i); doc.setTextColor(120); doc.setFontSize(8); doc.text(`Smart IoT Pro Report • Page ${i}/${doc.internal.getNumberOfPages()}`,15,292); } };
                const card = (x,y,w,h,c,t,v) => { doc.setFillColor(...c); doc.roundedRect(x,y,w,h,3,3,'F'); doc.setTextColor(255,255,255); doc.setFontSize(10); doc.text(t,x+4,y+7); doc.setFontSize(13); doc.text(String(v),x+4,y+16); };

                // Page 1 - Cover
                doc.setFillColor(15,23,42); doc.rect(0,0,W,H,'F');
                doc.setFillColor(59,130,246); doc.circle(35,35,12,'F');
                doc.setTextColor(255,255,255); doc.setFontSize(14); doc.text("IoT",30,38);
                doc.setFontSize(24); doc.text("SMART ANALYTICS PRO",55,55); doc.text("CORPORATE REPORT",50,72);
                doc.setDrawColor(96,165,250); doc.line(35,85,175,85);
                doc.setFontSize(11); doc.text(`User: ${username}`,20,120);
                doc.text(`Generated: ${new Date().toLocaleString('en-US')}`,20,128);
                doc.text(`Device: ESP32 Smart Monitoring`,20,136);

                // Generate QR Code
                const qrDiv = document.createElement('div');
                new QRCode(qrDiv, { text: window.location.href, width:100, height:100 });
                await new Promise(r=>setTimeout(r,300));
                const qrImg = qrDiv.querySelector('img')?.src || qrDiv.querySelector('canvas')?.toDataURL();
                if(qrImg) doc.addImage(qrImg,'PNG',145,110,35,35);

                // Page 2 - Dashboard
                doc.addPage(); addHeader("Executive Dashboard"); let y=30;
                card(15,y,42,22,[249,115,22],"TEMP",ct);
                card(62,y,42,22,[37,99,235],"SIGNAL",cs);
                card(109,y,42,22,[139,92,246],"AI SCORE",ai);
                card(156,y,39,22,[16,185,129],"STATUS",ds);
                y+=35;

                // Animated Gauge
                doc.setFontSize(14); doc.setTextColor(0); doc.text("AI Gauge",15,y);
                const cx=60, cy=y+35, rad=25;
                for(let f=0;f<5;f++){
                    if(f>0){ doc.setFillColor(255,255,255); doc.rect(30,y+5,60,60,'F'); }
                    doc.setDrawColor(220); doc.setLineWidth(6); doc.arc(cx,cy,rad,rad,180,360);
                    const angle=180+(sn*1.8*(f+1)/5); const radA=angle*Math.PI/180;
                    doc.setDrawColor(...(sn>70?[34,197,94]:sn>40?[245,158,11]:[220,38,38])); doc.setLineWidth(3+f*0.5);
                    doc.line(cx,cy,cx+rad*Math.cos(radA),cy+rad*Math.sin(radA));
                    doc.setFontSize(16); doc.text(ai,cx-7,cy+8);
                    await new Promise(r=>setTimeout(r,30));
                }

                // Page 3 - Charts
                doc.addPage(); addHeader("Analytics Charts"); y=28;
                const hc=document.getElementById('historyChart'), ac=document.getElementById('alertChart'), pc=document.getElementById('predictChart');
                doc.text("History",15,y); y+=5; if(hc) doc.addImage(hc.toDataURL('image/png'),'PNG',15,y,180,60); y+=70;
                doc.text("Alerts (3 curves)",15,y); y+=5; if(ac) doc.addImage(ac.toDataURL('image/png'),'PNG',15,y,180,55); y+=65;
                doc.text("Prediction",15,y); y+=5; if(pc) doc.addImage(pc.toDataURL('image/png'),'PNG',15,y,180,45);

                // Page 4 - Technical Metrics
                doc.addPage(); addHeader("Technical Metrics"); y=30;
                doc.autoTable({ startY:y, head:[['Metric','Value','Evaluation']], body:[
                    ['Temperature',ct,tn>CONFIG.TEMP_CRITICAL?'Critical':tn>CONFIG.TEMP_WARNING?'High':'Normal'],
                    ['Signal',cs,sgn<CONFIG.SIGNAL_CRITICAL?'Critical':sgn<CONFIG.SIGNAL_WARNING?'Weak':'Stable'],
                    ['AI Score',ai,sn>70?'Healthy':sn>40?'Warning':'Risk'],
                    ['Anomaly',an,an!=='None'?'⚠️ Active':'✅ Clear']
                ], theme:'grid', headStyles:{fillColor:[30,64,175]} });
                y=doc.lastAutoTable.finalY+40;
                doc.line(30,y,90,y); doc.text("Technical Supervisor",42,y+8);
                doc.line(120,y,180,y); doc.text("System Signature",137,y+8);

                // Page 5 - AI Conclusion
                doc.addPage(); addHeader("AI Conclusion"); y=35;
                let conclusion = sn>=80 ? "The monitored environment operates within optimal parameters. AI confirms healthy status and predictive stability. No immediate action required." : sn>=50 ? "Moderate operational instability detected. Preventive maintenance recommended within the next 24 hours." : "Critical anomalies detected. Immediate intervention required. System stability is compromised.";
                doc.setTextColor(0); doc.setFontSize(12); doc.text(doc.splitTextToSize(conclusion,170),15,y);
                y+=40;
                const insights=document.getElementById('insightsContainer')?.innerText||'', pred=document.getElementById('predictionsContainer')?.innerText||'', rec=document.getElementById('recommendationsContainer')?.innerText||'';
                doc.setFontSize(11); doc.setTextColor(30,64,175); doc.text("AI Insights:",15,y); doc.setTextColor(0); doc.setFontSize(10); doc.text(doc.splitTextToSize(insights,170),15,y+8);
                y+=30; doc.setTextColor(30,64,175); doc.setFontSize(11); doc.text("Predictions:",15,y); doc.setTextColor(0); doc.setFontSize(10); doc.text(doc.splitTextToSize(pred,170),15,y+8);
                y+=30; doc.setTextColor(30,64,175); doc.setFontSize(11); doc.text("Recommendations:",15,y); doc.setTextColor(0); doc.setFontSize(10); doc.text(doc.splitTextToSize(rec,170),15,y+8);
                
                addFooter();
                doc.save(`smart-analytics-pro-report-${Date.now()}.pdf`);
            } catch(e) { console.error("PDF Error:",e); alert("PDF generation failed. Please try again."); }
            finally { if(ld) ld.style.display='none'; }
        }

        function init() {
            initCharts();
            switchHistoryView('24h');
            loadAnalytics();
            setInterval(loadAnalytics, CONFIG.UPDATE_INTERVAL);
            setInterval(updateDeviceStatus, 5000);
            setInterval(updateUptime, 1000);
        }
        document.addEventListener('DOMContentLoaded', init);
    </script>
</body>
</html>
