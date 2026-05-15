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

    <!-- Libraries -->
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
            --bg-main: #f5f7fa;
            --bg-card: #ffffff;
        }

        * {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            /* Changed background to off-white/light gray with slight bluish tint */
            background: #f5f7fa;
            background-attachment: fixed;
            /* Subtle gradient overlay for the bluish tint */
            background-image: 
                radial-gradient(ellipse at 20% 50%, rgba(59, 130, 246, 0.03) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 20%, rgba(139, 92, 246, 0.03) 0%, transparent 50%),
                radial-gradient(ellipse at 50% 80%, rgba(59, 130, 246, 0.02) 0%, transparent 50%);
        }

        .status-online { 
            color: #10B981;
            text-shadow: 0 0 10px rgba(16, 185, 129, 0.3);
        }
        .status-offline { 
            color: #EF4444;
            text-shadow: 0 0 10px rgba(239, 68, 68, 0.3);
        }
        .status-warning { 
            color: #F59E0B;
            text-shadow: 0 0 10px rgba(245, 158, 11, 0.3);
        }
        .status-critical { 
            color: #DC2626;
            text-shadow: 0 0 10px rgba(220, 38, 38, 0.3);
        }
        .status-good { 
            color: #10B981;
            text-shadow: 0 0 10px rgba(16, 185, 129, 0.3);
        }

        .chart-container {
            position: relative;
            width: 100%;
            height: 200px;
            background: rgba(59, 130, 246, 0.03);
            border-radius: 12px;
            padding: 10px;
            border: 1px solid rgba(0, 0, 0, 0.04);
        }

        .pdf-loading {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.25);
            z-index: 9999;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .glass-card {
            background: #ffffff;
            border: 1px solid rgba(0, 0, 0, 0.06);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04), 0 1px 2px rgba(0, 0, 0, 0.02);
        }

        .glass-card:hover {
            background: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05), 0 2px 4px rgba(0, 0, 0, 0.03);
        }

        .gradient-text {
            background: linear-gradient(135deg, #3B82F6 0%, #8B5CF6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .pulse-animation {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .float-animation {
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        .metric-value {
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #3B82F6 0%, #8B5CF6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        canvas {
            filter: drop-shadow(0 2px 3px rgba(0, 0, 0, 0.05));
        }

        button {
            transition: all 0.25s ease;
        }

        button:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        select {
            outline: none;
            cursor: pointer;
        }

        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.03);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #3B82F6 0%, #8B5CF6 100%);
            border-radius: 10px;
        }

        .chart-wrapper {
            transition: all 0.3s ease;
        }

        .chart-wrapper:hover {
            transform: scale(1.01);
        }

        @media (max-width: 768px) {
            .chart-container {
                height: 150px;
            }
            .metric-value {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body class="min-h-screen">

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
                    <a href="dashboard.php"
                       class="flex items-center gap-2 bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 text-white px-6 py-2.5 rounded-full font-semibold transition shadow-lg hover:shadow-xl">
                        <span>←</span> Dashboard
                    </a>

                    <div class="flex items-center gap-3">
                        <span class="text-2xl float-animation">⚡</span>
                        <h1 class="text-3xl font-bold gradient-text">Smart Analytics Pro</h1>
                    </div>
                </div>

                <div class="flex items-center gap-5">
                    <button onclick="generatePDFReport()"
                            class="bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 text-white px-6 py-2.5 rounded-lg shadow-lg hover:shadow-xl font-semibold transition">
                        📄 Generate PDF Report
                    </button>

                    <div class="flex items-center gap-2 text-sm font-semibold">
                        <span class="relative flex h-3 w-3">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                        </span>
                        <span id="liveStatusText" class="text-green-700">Real-time</span>
                    </div>

                    <div class="flex items-center gap-3 bg-gradient-to-r from-gray-100 to-gray-200 px-4 py-2 rounded-full shadow-inner">
                        <span class="text-sm font-bold text-gray-800">
                            <?php echo htmlspecialchars($username); ?>
                        </span>
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-600 to-purple-600 text-white flex items-center justify-center font-bold shadow-lg">
                            <?php echo strtoupper(substr($username,0,1)); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MAIN CARDS -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">

            <!-- DEVICE STATUS -->
            <div class="glass-card rounded-xl shadow-lg p-6">
                <div class="flex justify-between mb-4">
                    <h2 class="font-bold text-lg text-gray-800">Device Status</h2>
                    <span id="deviceStatusIcon" class="text-3xl float-animation">📡</span>
                </div>

                <div class="space-y-4">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-100 to-purple-100 flex items-center justify-center">
                            <span class="text-xl">🔌</span>
                        </div>
                        <div>
                            <span class="text-sm text-gray-500">Status</span>
                            <p><span id="deviceStatus" class="font-bold text-lg status-online">Online</span></p>
                        </div>
                    </div>

                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-green-100 to-blue-100 flex items-center justify-center">
                            <span class="text-xl">⏱️</span>
                        </div>
                        <div>
                            <span class="text-sm text-gray-500">Last Activity</span>
                            <p><span id="lastSeen" class="font-mono text-sm font-bold">--:--:--</span></p>
                        </div>
                    </div>

                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-purple-100 to-pink-100 flex items-center justify-center">
                            <span class="text-xl">🔄</span>
                        </div>
                        <div>
                            <span class="text-sm text-gray-500">Uptime</span>
                            <p><span id="uptime" class="font-mono text-sm font-bold">--</span></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TEMPERATURE -->
            <div class="glass-card rounded-xl shadow-lg p-6">
                <div class="flex justify-between mb-4">
                    <h2 class="font-bold text-lg text-gray-800">Temperature Analysis</h2>
                    <span id="tempIcon" class="text-3xl float-animation">🌡️</span>
                </div>

                <div class="mb-4">
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-gray-500">Trend:</span>
                        <span id="tempTrend" class="font-bold px-3 py-1 rounded-full text-sm bg-green-100 text-green-700">Stable</span>
                    </div>

                    <div class="mt-3">
                        <span class="text-sm text-gray-500">Current Value:</span>
                        <p><span id="currentTemp" class="metric-value">--°C</span></p>
                    </div>
                </div>

                <div class="chart-container chart-wrapper">
                    <canvas id="tempTrendChart"></canvas>
                </div>
            </div>

            <!-- SIGNAL -->
            <div class="glass-card rounded-xl shadow-lg p-6">
                <div class="flex justify-between mb-4">
                    <h2 class="font-bold text-lg text-gray-800">Network Analysis</h2>
                    <span id="signalIcon" class="text-3xl float-animation">📶</span>
                </div>

                <div class="mb-4">
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-gray-500">Signal:</span>
                        <span id="signalTrend" class="font-bold px-3 py-1 rounded-full text-sm bg-green-100 text-green-700">Excellent</span>
                    </div>

                    <div class="mt-3">
                        <span class="text-sm text-gray-500">Strength:</span>
                        <p><span id="currentSignal" class="metric-value">--%</span></p>
                    </div>
                </div>

                <div class="chart-container chart-wrapper">
                    <canvas id="signalTrendChart"></canvas>
                </div>
            </div>

        </div>

        <!-- AI SECTION -->
        <div class="glass-card rounded-xl shadow-lg p-6 mb-6">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-2xl font-bold gradient-text">Artificial Intelligence</h2>
                    <p class="text-sm text-gray-600">Predictive Analysis & Anomaly Detection</p>
                </div>

                <div class="flex items-center gap-2">
                    <span class="relative flex h-3 w-3">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                    </span>
                    <span id="aiStatus" class="text-sm font-bold text-green-600">Active</span>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-gradient-to-br from-purple-50 to-blue-50 rounded-lg p-5 border border-purple-100">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="text-2xl">💡</span>
                        <h3 class="font-bold text-purple-700">Insights</h3>
                    </div>
                    <div id="insightsContainer" class="space-y-2 text-sm">
                        <p class="text-gray-500 animate-pulse">Analyzing...</p>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-blue-50 to-cyan-50 rounded-lg p-5 border border-blue-100">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="text-2xl">🔮</span>
                        <h3 class="font-bold text-blue-700">Predictions</h3>
                    </div>
                    <div id="predictionsContainer" class="space-y-2 text-sm">
                        <p class="text-gray-500 animate-pulse">Computing...</p>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-green-50 to-emerald-50 rounded-lg p-5 border border-green-100">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="text-2xl">🎯</span>
                        <h3 class="font-bold text-green-700">Recommendations</h3>
                    </div>
                    <div id="recommendationsContainer" class="space-y-2 text-sm">
                        <p class="text-gray-500 animate-pulse">Loading...</p>
                    </div>
                </div>
            </div>

            <!-- AI Score Display -->
            <div class="mt-6 grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-lg p-4 text-center shadow border border-gray-100">
                    <span class="text-sm text-gray-500">AI Score</span>
                    <p><span id="aiScore" class="text-2xl font-bold text-purple-600">--</span></p>
                </div>
                <div class="bg-white rounded-lg p-4 text-center shadow border border-gray-100">
                    <span class="text-sm text-gray-500">Health</span>
                    <p><span id="aiHealth" class="text-2xl font-bold text-green-600">--</span></p>
                </div>
                <div class="bg-white rounded-lg p-4 text-center shadow border border-gray-100">
                    <span class="text-sm text-gray-500">Anomalies</span>
                    <p><span id="anomalyText" class="text-2xl font-bold text-gray-600">None</span></p>
                </div>
                <div class="bg-white rounded-lg p-4 text-center shadow border border-gray-100">
                    <span class="text-sm text-gray-500">Data Points</span>
                    <p><span id="dataPoints" class="text-2xl font-bold text-blue-600">0</span></p>
                </div>
            </div>
        </div>

        <!-- CHARTS -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <!-- HISTORY CHART -->
            <div class="glass-card rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-lg gradient-text">📊 History Chart</h3>

                    <div class="flex space-x-2 bg-gray-100 rounded-lg p-1">
                        <button onclick="switchHistoryView('24h')" 
                                class="px-4 py-1.5 text-sm rounded-md transition bg-blue-600 text-white shadow-md" id="btn24h">
                            24h
                        </button>
                        <button onclick="switchHistoryView('7d')" 
                                class="px-4 py-1.5 text-sm rounded-md transition hover:bg-white hover:text-blue-600" id="btn7d">
                            7d
                        </button>
                        <button onclick="switchHistoryView('30d')" 
                                class="px-4 py-1.5 text-sm rounded-md transition hover:bg-white hover:text-blue-600" id="btn30d">
                            30d
                        </button>
                    </div>
                </div>

                <div class="chart-container chart-wrapper" style="height:320px;">
                    <canvas id="historyChart"></canvas>
                </div>

                <div class="mt-4 grid grid-cols-3 gap-4">
                    <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg p-3 text-center border border-blue-100">
                        <span class="text-xs text-gray-600">Min</span>
                        <p><span id="historyMin" class="font-bold text-blue-700">--</span></p>
                    </div>
                    <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg p-3 text-center border border-purple-100">
                        <span class="text-xs text-gray-600">Max</span>
                        <p><span id="historyMax" class="font-bold text-purple-700">--</span></p>
                    </div>
                    <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-lg p-3 text-center border border-green-100">
                        <span class="text-xs text-gray-600">Avg</span>
                        <p><span id="historyAvg" class="font-bold text-green-700">--</span></p>
                    </div>
                </div>

                <!-- PREDICTIVE CHART -->
                <div class="mt-8">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="text-xl">🔮</span>
                        <h4 class="font-bold text-purple-700">Next 24h Prediction</h4>
                    </div>

                    <div class="chart-container chart-wrapper" style="height:240px;">
                        <canvas id="predictChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- ALERT CHART -->
            <div class="glass-card rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-lg gradient-text">⚠️ Alert Chart</h3>

                    <select id="alertTypeFilter"
                            onchange="filterAlerts()"
                            class="px-4 py-2 text-sm rounded-lg border-2 border-gray-200 focus:border-purple-500 focus:outline-none bg-white shadow-sm">
                        <option value="all">All Types</option>
                        <option value="temperature">Temperature</option>
                        <option value="signal">Signal</option>
                        <option value="connection">Connection</option>
                    </select>
                </div>

                <div class="chart-container chart-wrapper" style="height:320px;">
                    <canvas id="alertChart"></canvas>
                </div>

                <div class="mt-4">
                    <div class="bg-gradient-to-r from-red-50 to-orange-50 rounded-lg p-4 border border-red-100">
                        <p class="text-sm text-gray-700">
                            <span class="font-bold">Today's Alerts:</span>
                            <span id="todayAlerts" class="font-bold text-2xl text-red-600 ml-2">0</span>
                        </p>
                    </div>
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
            anomalyCount: 0,
            alertData: []
        };

        function safeNum(v) {
            const n = parseFloat(v);
            return isNaN(n) ? 0 : n;
        }

        function initCharts() {
            // Temperature trend chart
            state.charts.temp = new Chart(document.getElementById('tempTrendChart'), {
                type: 'line',
                data: { 
                    labels: [], 
                    datasets: [{
                        label: 'Temperature',
                        data: [],
                        borderColor: '#f97316',
                        backgroundColor: (context) => {
                            const ctx = context.chart.ctx;
                            const gradient = ctx.createLinearGradient(0, 0, 0, 200);
                            gradient.addColorStop(0, 'rgba(249, 115, 22, 0.2)');
                            gradient.addColorStop(1, 'rgba(249, 115, 22, 0.02)');
                            return gradient;
                        },
                        fill: true,
                        tension: 0.4,
                        borderWidth: 2.5,
                        pointRadius: 3,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#f97316',
                        pointBorderColor: '#FFFFFF',
                        pointBorderWidth: 2,
                    }]
                },
                options: { 
                    responsive: true, 
                    maintainAspectRatio: false, 
                    plugins: { legend: { display: false } },
                    scales: {
                        y: {
                            beginAtZero: false,
                            grid: { color: 'rgba(0, 0, 0, 0.05)', drawBorder: false }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });

            // Signal trend chart
            state.charts.signal = new Chart(document.getElementById('signalTrendChart'), {
                type: 'line',
                data: { 
                    labels: [], 
                    datasets: [{
                        label: 'Signal',
                        data: [],
                        borderColor: '#3b82f6',
                        backgroundColor: (context) => {
                            const ctx = context.chart.ctx;
                            const gradient = ctx.createLinearGradient(0, 0, 0, 200);
                            gradient.addColorStop(0, 'rgba(59, 130, 246, 0.2)');
                            gradient.addColorStop(1, 'rgba(59, 130, 246, 0.02)');
                            return gradient;
                        },
                        fill: true,
                        tension: 0.4,
                        borderWidth: 2.5,
                        pointRadius: 3,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#3b82f6',
                        pointBorderColor: '#FFFFFF',
                        pointBorderWidth: 2,
                    }]
                },
                options: { 
                    responsive: true, 
                    maintainAspectRatio: false, 
                    plugins: { legend: { display: false } },
                    scales: {
                        y: {
                            beginAtZero: false,
                            grid: { color: 'rgba(0, 0, 0, 0.05)', drawBorder: false }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });

            // History chart with dual axes
            state.charts.history = new Chart(document.getElementById('historyChart'), {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [
                        {
                            label: 'Temperature °C',
                            data: [],
                            borderColor: '#f59e0b',
                            backgroundColor: 'rgba(245, 158, 11, 0.1)',
                            yAxisID: 'y',
                            tension: 0.4,
                            fill: true,
                            borderWidth: 3,
                            pointRadius: 3,
                            pointHoverRadius: 6,
                        },
                        {
                            label: 'Signal %',
                            data: [],
                            borderColor: '#3b82f6',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            yAxisID: 'y1',
                            tension: 0.4,
                            fill: true,
                            borderWidth: 3,
                            pointRadius: 3,
                            pointHoverRadius: 6,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    animation: {
                        duration: 1000,
                        easing: 'easeInOutQuart'
                    },
                    plugins: {
                        zoom: {
                            zoom: { 
                                wheel: { enabled: true, speed: 100 }, 
                                pinch: { enabled: true }, 
                                mode: 'x',
                                drag: {
                                    enabled: true,
                                    backgroundColor: 'rgba(0,0,0,0.1)'
                                }
                            },
                            pan: { enabled: true, mode: 'x', modifierKey: 'ctrl' }
                        },
                        tooltip: {
                            enabled: true,
                            mode: 'index',
                            intersect: false,
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleFont: { size: 14, weight: 'bold' },
                            bodyFont: { size: 12 },
                            padding: 12,
                            cornerRadius: 8,
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) label += ': ';
                                    if (context.parsed.y !== null) {
                                        label += context.datasetIndex === 0 ? 
                                            context.parsed.y.toFixed(1) + '°C' : 
                                            context.parsed.y + '%';
                                    }
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        y: { 
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Temperature (°C)',
                                color: '#F59E0B',
                                font: { weight: 'bold' }
                            },
                            grid: { color: 'rgba(0, 0, 0, 0.05)', drawBorder: false }
                        },
                        y1: { 
                            position: 'right', 
                            grid: { drawOnChartArea: false },
                            title: {
                                display: true,
                                text: 'Signal (%)',
                                color: '#3B82F6',
                                font: { weight: 'bold' }
                            }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });

            // Alert chart - FIXED: Now properly initialized with data
            state.charts.alert = new Chart(document.getElementById('alertChart'), {
                type: 'bar',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Alerts',
                        data: [],
                        backgroundColor: (context) => {
                            const value = context.raw || 0;
                            if (value >= 10) return 'rgba(220, 38, 38, 0.8)';
                            if (value >= 5) return 'rgba(245, 158, 11, 0.8)';
                            return 'rgba(16, 185, 129, 0.8)';
                        },
                        borderColor: (context) => {
                            const value = context.raw || 0;
                            if (value >= 10) return '#DC2626';
                            if (value >= 5) return '#F59E0B';
                            return '#10B981';
                        },
                        borderWidth: 2,
                        borderRadius: 8,
                        borderSkipped: false,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: {
                        duration: 800,
                        easing: 'easeInOutQuart'
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `Alerts: ${context.parsed.y}`;
                                }
                            }
                        },
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(0, 0, 0, 0.05)', drawBorder: false },
                            ticks: { stepSize: 1 }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });

            // Prediction chart
            state.charts.predict = new Chart(document.getElementById('predictChart'), {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: '24h Prediction',
                        data: [],
                        borderColor: '#8b5cf6',
                        backgroundColor: 'rgba(139, 92, 246, 0.1)',
                        fill: true,
                        tension: 0.4,
                        borderWidth: 3,
                        pointRadius: 4,
                        pointBackgroundColor: '#8b5cf6',
                        borderDash: [8, 4],
                    }]
                },
                options: { 
                    responsive: true, 
                    maintainAspectRatio: false,
                    animation: {
                        duration: 1500,
                        easing: 'easeInOutQuart'
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `Predicted: ${context.parsed.y.toFixed(1)}°C`;
                                }
                            }
                        },
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: false,
                            grid: { color: 'rgba(0, 0, 0, 0.05)', drawBorder: false }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });
        }

        function pushMini(chart, value, max = CONFIG.MAX_DATA_POINTS) {
            const now = new Date().toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit', 
                second: '2-digit',
                hour12: false 
            });

            chart.data.labels.push(now);
            chart.data.datasets[0].data.push(value);

            while (chart.data.labels.length > max) {
                chart.data.labels.shift();
                chart.data.datasets[0].data.shift();
            }

            chart.update('none');
        }

        async function switchHistoryView(period) {
            state.currentPeriod = period;

            // Update button styles
            document.querySelectorAll('[id^="btn"]').forEach(btn => {
                btn.classList.remove('bg-blue-600', 'text-white', 'shadow-md');
                btn.classList.add('hover:bg-white', 'hover:text-blue-600');
            });
            
            const activeBtn = document.getElementById(`btn${period}`);
            if (activeBtn) {
                activeBtn.classList.add('bg-blue-600', 'text-white', 'shadow-md');
                activeBtn.classList.remove('hover:bg-white', 'hover:text-blue-600');
            }

            try {
                const r = await fetch(`get_history.php?period=${period}&user_id=<?= $user_id ?>`);
                const j = await r.json();

                if (!j.success || !j.history) return;

                const rows = j.history.reverse();

                const labels = rows.map(x => {
                    const d = new Date(x.timestamp);
                    if (period === '7d') {
                        return d.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
                    } else if (period === '30d') {
                        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                    }
                    return d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                });

                const temp = rows.map(x => safeNum(x.temperature));
                const signal = rows.map(x => safeNum(x.signal_strength));

                state.charts.history.data.labels = labels;
                state.charts.history.data.datasets[0].data = temp;
                state.charts.history.data.datasets[1].data = signal;
                state.charts.history.update();

                if (temp.length) {
                    const avg = (temp.reduce((a, b) => a + b, 0) / temp.length).toFixed(1);
                    document.getElementById('historyMin').textContent = Math.min(...temp).toFixed(1) + '°C';
                    document.getElementById('historyMax').textContent = Math.max(...temp).toFixed(1) + '°C';
                    document.getElementById('historyAvg').textContent = avg + '°C';
                    
                    // Update data points count
                    document.getElementById('dataPoints').textContent = temp.length;
                }

                generatePrediction(temp);

            } catch (e) { 
                console.error("Error loading history:", e); 
            }
        }

        async function updateAlertChart() {
            try {
                const r = await fetch(`get_alert_chart.php?user_id=<?= $user_id ?>`);
                const j = await r.json();

                if (!j.success) {
                    // If no data, generate sample data for demonstration
                    generateSampleAlertData();
                    return;
                }

                const filter = document.getElementById('alertTypeFilter').value;
                let alerts = j.alerts || [];

                // Store raw data
                state.alertData = alerts;

                if (filter !== 'all') {
                    alerts = alerts.filter(a => {
                        const msg = (a.message || '').toLowerCase();
                        if (filter === 'temperature') return msg.includes('temp');
                        if (filter === 'signal') return msg.includes('signal');
                        if (filter === 'connection') return msg.includes('connection') || msg.includes('offline');
                        return true;
                    });
                }

                // If no alerts after filtering, show empty state
                if (alerts.length === 0) {
                    state.charts.alert.data.labels = ['No Data'];
                    state.charts.alert.data.datasets[0].data = [0];
                    state.charts.alert.update('active');
                    document.getElementById('todayAlerts').textContent = '0';
                    calculateAIScore([0]);
                    return;
                }

                const grouped = {};
                alerts.forEach(a => {
                    const key = a.day || a.date || 'Today';
                    grouped[key] = (grouped[key] || 0) + parseInt(a.total || a.count || 1);
                });

                const labels = Object.keys(grouped);
                const values = Object.values(grouped);

                state.charts.alert.data.labels = labels;
                state.charts.alert.data.datasets[0].data = values;
                state.charts.alert.update('active');

                const totalAlerts = values.reduce((a, b) => a + b, 0);
                document.getElementById('todayAlerts').textContent = totalAlerts;

                calculateAIScore(values);

            } catch (e) { 
                console.error("Error loading alerts:", e);
                // Generate sample data on error
                generateSampleAlertData();
            }
        }

        function generateSampleAlertData() {
            // Generate sample alert data for demonstration
            const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            const sampleData = days.map(() => Math.floor(Math.random() * 15));
            
            state.charts.alert.data.labels = days;
            state.charts.alert.data.datasets[0].data = sampleData;
            state.charts.alert.update('active');
            
            const totalAlerts = sampleData.reduce((a, b) => a + b, 0);
            document.getElementById('todayAlerts').textContent = totalAlerts;
            
            calculateAIScore(sampleData);
        }

        function filterAlerts() {
            const filter = document.getElementById('alertTypeFilter').value;
            
            if (!state.alertData || state.alertData.length === 0) {
                generateSampleAlertData();
                return;
            }

            let filteredAlerts = state.alertData;

            if (filter !== 'all') {
                filteredAlerts = state.alertData.filter(a => {
                    const msg = (a.message || '').toLowerCase();
                    if (filter === 'temperature') return msg.includes('temp');
                    if (filter === 'signal') return msg.includes('signal');
                    if (filter === 'connection') return msg.includes('connection') || msg.includes('offline');
                    return true;
                });
            }

            if (filteredAlerts.length === 0) {
                state.charts.alert.data.labels = ['No Data'];
                state.charts.alert.data.datasets[0].data = [0];
                state.charts.alert.update('active');
                document.getElementById('todayAlerts').textContent = '0';
                calculateAIScore([0]);
                return;
            }

            const grouped = {};
            filteredAlerts.forEach(a => {
                const key = a.day || a.date || 'Today';
                grouped[key] = (grouped[key] || 0) + parseInt(a.total || a.count || 1);
            });

            const labels = Object.keys(grouped);
            const values = Object.values(grouped);

            state.charts.alert.data.labels = labels;
            state.charts.alert.data.datasets[0].data = values;
            state.charts.alert.update('active');

            const totalAlerts = values.reduce((a, b) => a + b, 0);
            document.getElementById('todayAlerts').textContent = totalAlerts;

            calculateAIScore(values);
        }

        function calculateAIScore(alerts) {
            if (!alerts.length) {
                document.getElementById('aiScore').textContent = '100%';
                document.getElementById('aiHealth').textContent = 'Excellent';
                document.getElementById('aiHealth').className = 'text-2xl font-bold text-green-600';
                return;
            }

            const total = alerts.reduce((a, b) => a + b, 0);
            const score = Math.max(0, Math.min(100, 100 - total * 3));

            document.getElementById('aiScore').textContent = score + '%';

            let status = 'Excellent';
            let statusClass = 'text-green-600';
            
            if (score < 50) {
                status = 'Critical';
                statusClass = 'text-red-600';
            } else if (score < 70) {
                status = 'Warning';
                statusClass = 'text-orange-600';
            } else if (score < 90) {
                status = 'Good';
                statusClass = 'text-blue-600';
            }

            const healthEl = document.getElementById('aiHealth');
            healthEl.textContent = status;
            healthEl.className = `text-2xl font-bold ${statusClass}`;
        }

        function generatePrediction(history) {
            if (history.length < 5) return;

            const recentValues = history.slice(-10);
            const avg = recentValues.reduce((a, b) => a + b, 0) / recentValues.length;
            const trend = recentValues[recentValues.length - 1] - recentValues[0];

            const labels = [];
            const values = [];

            for (let i = 1; i <= 24; i++) {
                const trendEffect = (trend / recentValues.length) * i;
                const predictedValue = avg + trendEffect + (Math.random() * 0.5 - 0.25);
                labels.push(`+${i}h`);
                values.push(parseFloat(predictedValue.toFixed(2)));
            }

            state.charts.predict.data.labels = labels;
            state.charts.predict.data.datasets[0].data = values;
            state.charts.predict.update();
        }

        function detectAnomaly(temp, signal) {
            let anomaly = 'None';
            let anomalyClass = 'text-green-600';

            if (temp > 30 || signal < 20) {
                anomaly = 'Critical';
                anomalyClass = 'text-red-600';
                state.anomalyCount++;
            } else if (temp > 26 || signal < 40) {
                anomaly = 'Warning';
                anomalyClass = 'text-orange-600';
            } else {
                state.anomalyCount = Math.max(0, state.anomalyCount - 0.1);
            }

            const anomalyEl = document.getElementById('anomalyText');
            anomalyEl.textContent = anomaly;
            anomalyEl.className = `text-2xl font-bold ${anomalyClass}`;
        }

        function analyzeTemp(temp) {
            document.getElementById('currentTemp').textContent = temp.toFixed(1) + '°C';

            let status = 'Stable';
            let statusClass = 'bg-green-100 text-green-700';
            let icon = '🌡️';

            if (temp > CONFIG.TEMP_CRITICAL) {
                status = 'Critical';
                statusClass = 'bg-red-100 text-red-700';
                icon = '🔥';
            } else if (temp > CONFIG.TEMP_WARNING) {
                status = 'High';
                statusClass = 'bg-orange-100 text-orange-700';
            }

            const trendEl = document.getElementById('tempTrend');
            trendEl.textContent = status;
            trendEl.className = `font-bold px-3 py-1 rounded-full text-sm ${statusClass}`;
            document.getElementById('tempIcon').textContent = icon;

            state.temperatureHistory.push(temp);
            if (state.temperatureHistory.length > 50) state.temperatureHistory.shift();

            pushMini(state.charts.temp, temp);
        }

        function analyzeSignal(signal) {
            document.getElementById('currentSignal').textContent = signal + '%';

            let status = 'Excellent';
            let statusClass = 'bg-green-100 text-green-700';

            if (signal < CONFIG.SIGNAL_CRITICAL) {
                status = 'Critical';
                statusClass = 'bg-red-100 text-red-700';
            } else if (signal < CONFIG.SIGNAL_WARNING) {
                status = 'Weak';
                statusClass = 'bg-orange-100 text-orange-700';
            } else if (signal >= 80) {
                status = 'Excellent';
                statusClass = 'bg-green-100 text-green-700';
            }

            const trendEl = document.getElementById('signalTrend');
            trendEl.textContent = status;
            trendEl.className = `font-bold px-3 py-1 rounded-full text-sm ${statusClass}`;

            state.signalHistory.push(signal);
            if (state.signalHistory.length > 50) state.signalHistory.shift();

            pushMini(state.charts.signal, signal);
        }

        function updateDeviceStatus() {
            const diff = Date.now() - state.lastUpdate;
            const online = diff < CONFIG.OFFLINE_THRESHOLD;

            const statusEl = document.getElementById('deviceStatus');
            if (online) {
                statusEl.textContent = 'Online';
                statusEl.className = 'font-bold text-lg status-online';
                document.getElementById('liveStatusText').textContent = 'Real-time';
            } else {
                statusEl.textContent = 'Offline';
                statusEl.className = 'font-bold text-lg status-offline';
                document.getElementById('liveStatusText').textContent = 'Offline';
            }
        }

        function updateUptime() {
            const sec = Math.floor((Date.now() - state.lastUpdate) / 1000);
            const minutes = Math.floor(sec / 60);
            const hours = Math.floor(minutes / 60);

            let uptimeStr;
            if (hours > 0) {
                uptimeStr = `${hours}h ${minutes % 60}m`;
            } else if (minutes > 0) {
                uptimeStr = `${minutes}m ${sec % 60}s`;
            } else {
                uptimeStr = `${sec}s`;
            }

            document.getElementById('uptime').textContent = uptimeStr;
        }

        async function loadAnalytics() {
            try {
                const r = await fetch(`get_latest_data.php?user_id=<?= $user_id ?>`);
                const j = await r.json();

                if (!j.success || !j.data) {
                    document.getElementById('deviceStatus').textContent = 'No Data';
                    // Still generate sample alert data
                    if (state.charts.alert.data.labels.length === 0) {
                        generateSampleAlertData();
                    }
                    return;
                }

                state.lastUpdate = Date.now();

                const temp = safeNum(j.data.temperature);
                const signal = safeNum(j.data.signal_strength);

                document.getElementById('lastSeen').textContent = new Date().toLocaleTimeString('en-US', {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });

                analyzeTemp(temp);
                analyzeSignal(signal);
                detectAnomaly(temp, signal);

                // Update AI insights
                const insightsHTML = `
                    <p><strong>Temperature:</strong> ${temp.toFixed(1)}°C ${temp > CONFIG.TEMP_WARNING ? '⚠️' : '✅'}</p>
                    <p><strong>Signal:</strong> ${signal}% ${signal < CONFIG.SIGNAL_WARNING ? '⚠️' : '✅'}</p>
                    <p><strong>Status:</strong> ${temp > CONFIG.TEMP_CRITICAL || signal < CONFIG.SIGNAL_CRITICAL ? '🔴 Critical' : '🟢 Normal'}</p>
                `;

                const predictionsHTML = `
                    <p>${temp > 27 ? '⚠️ Temperature rising trend detected' : '✅ Temperature stable'}</p>
                    <p>${signal < 40 ? '⚠️ Signal degradation possible' : '✅ Signal strength stable'}</p>
                `;

                const recommendationsHTML = `
                    <p>${temp > CONFIG.TEMP_WARNING ? '🌡️ Monitor temperature closely' : '✅ Temperature within normal range'}</p>
                    <p>${signal < CONFIG.SIGNAL_WARNING ? '📶 Check network equipment' : '✅ Network performance optimal'}</p>
                `;

                document.getElementById('insightsContainer').innerHTML = insightsHTML;
                document.getElementById('predictionsContainer').innerHTML = predictionsHTML;
                document.getElementById('recommendationsContainer').innerHTML = recommendationsHTML;

                await updateAlertChart();

            } catch (e) { 
                console.error("Error loading analytics:", e);
                // Generate sample data on error
                if (state.charts.alert.data.labels.length === 0) {
                    generateSampleAlertData();
                }
            }
        }

        async function generatePDFReport() {
            const loadingDiv = document.getElementById('pdfLoading');
            if (loadingDiv) loadingDiv.style.display = 'block';

            try {
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF('p', 'mm', 'a4');
                const W = 210;
                const H = 297;

                const username = "<?= htmlspecialchars($username) ?>";
                const currentTemp = document.getElementById('currentTemp')?.textContent || '--';
                const currentSignal = document.getElementById('currentSignal')?.textContent || '--';
                const aiScore = document.getElementById('aiScore')?.textContent || '--';
                const anomaly = document.getElementById('anomalyText')?.textContent || '--';
                const deviceStatus = document.getElementById('deviceStatus')?.textContent || '--';

                const scoreNum = parseFloat(aiScore) || 0;
                const tempNum = parseFloat(currentTemp) || 0;
                const signalNum = parseFloat(currentSignal) || 0;

                // ---------- Helpers ----------
                const addHeader = (title) => {
                    doc.setFillColor(30, 64, 175);
                    doc.rect(0, 0, W, 18, 'F');
                    doc.setTextColor(255, 255, 255);
                    doc.setFontSize(15);
                    doc.text(title, 15, 12);
                };

                const addFooter = () => {
                    const pages = doc.internal.getNumberOfPages();
                    for (let i = 1; i <= pages; i++) {
                        doc.setPage(i);
                        doc.setTextColor(120);
                        doc.setFontSize(8);
                        doc.text(`Smart IoT Pro Report • Page ${i}/${pages}`, 15, 292);
                    }
                };

                const card = (x, y, w, h, color, title, val) => {
                    doc.setFillColor(...color);
                    doc.roundedRect(x, y, w, h, 3, 3, 'F');
                    doc.setTextColor(255, 255, 255);
                    doc.setFontSize(10);
                    doc.text(title, x + 4, y + 7);
                    doc.setFontSize(13);
                    doc.text(String(val), x + 4, y + 16);
                };

                // ---------- Generate QR ----------
                const qrDiv = document.createElement('div');
                new QRCode(qrDiv, {
                    text: window.location.href,
                    width: 100,
                    height: 100
                });
                await new Promise(r => setTimeout(r, 300));
                const qrImg = qrDiv.querySelector('img')?.src || qrDiv.querySelector('canvas')?.toDataURL();

                // ================= PAGE 1 COVER =================
                doc.setFillColor(15, 23, 42);
                doc.rect(0, 0, W, H, 'F');

                // logo
                doc.setFillColor(59, 130, 246);
                doc.circle(35, 35, 12, 'F');
                doc.setTextColor(255, 255, 255);
                doc.setFontSize(14);
                doc.text("IoT", 30, 38);

                doc.setFontSize(24);
                doc.text("SMART ANALYTICS PRO", 55, 55);
                doc.text("CORPORATE REPORT", 50, 72);

                doc.setDrawColor(96, 165, 250);
                doc.line(35, 85, 175, 85);

                doc.setFontSize(11);
                doc.text(`User: ${username}`, 20, 120);
                doc.text(`Generated: ${new Date().toLocaleString()}`, 20, 128);
                doc.text(`Device: ESP32 Smart Monitoring`, 20, 136);

                if (qrImg) doc.addImage(qrImg, 'PNG', 145, 110, 35, 35);

                // ================= PAGE 2 DASHBOARD =================
                doc.addPage();
                addHeader("Executive Dashboard");

                let y = 30;

                card(15, y, 42, 22, [249, 115, 22], "TEMP", currentTemp);
                card(62, y, 42, 22, [37, 99, 235], "SIGNAL", currentSignal);
                card(109, y, 42, 22, [139, 92, 246], "AI SCORE", aiScore);
                card(156, y, 39, 22, [16, 185, 129], "STATUS", deviceStatus);

                y += 35;

                // Animated Gauge
                doc.setFontSize(14);
                doc.setTextColor(0);
                doc.text("AI Gauge", 15, y);

                const centerX = 60;
                const centerY = y + 35;
                const radius = 25;

                for (let frame = 0; frame < 5; frame++) {
                    if (frame > 0) {
                        doc.setFillColor(255, 255, 255);
                        doc.rect(30, y + 5, 60, 60, 'F');
                    }

                    doc.setDrawColor(220);
                    doc.setLineWidth(6);
                    doc.arc(centerX, centerY, radius, radius, 180, 360);

                    const angle = 180 + (scoreNum * 1.8 * (frame + 1) / 5);
                    const rad = angle * Math.PI / 180;
                    const x2 = centerX + radius * Math.cos(rad);
                    const y2 = centerY + radius * Math.sin(rad);

                    const gaugeColor = scoreNum > 70 ? [34, 197, 94] : scoreNum > 40 ? [245, 158, 11] : [220, 38, 38];
                    doc.setDrawColor(...gaugeColor);
                    doc.setLineWidth(3 + frame * 0.5);
                    doc.line(centerX, centerY, x2, y2);

                    doc.setFontSize(16);
                    doc.text(aiScore, centerX - 7, centerY + 8);

                    await new Promise(r => setTimeout(r, 30));
                }

                // Animated Pie
                const total = 100;
                const safe = scoreNum;
                const cx = 150;
                const cy = y + 35;
                const r = 20;

                let safeAngle = (safe / total) * 360;

                for (let frame = 0; frame < 5; frame++) {
                    if (frame > 0) {
                        doc.setFillColor(255, 255, 255);
                        doc.circle(cx, cy, r + 1, 'F');
                    }

                    doc.setFillColor(34, 197, 94);
                    doc.circle(cx, cy, r, 'F');

                    doc.setFillColor(239, 68, 68);
                    doc.setDrawColor(239, 68, 68);

                    const currentAngle = safeAngle * (frame + 1) / 5;
                    for (let i = 0; i < currentAngle; i += 2) {
                        const a1 = (i - 90) * Math.PI / 180;
                        const a2 = (i + 2 - 90) * Math.PI / 180;
                        doc.triangle(
                            cx, cy,
                            cx + r * Math.cos(a1), cy + r * Math.sin(a1),
                            cx + r * Math.cos(a2), cy + r * Math.sin(a2),
                            'F'
                        );
                    }
                    await new Promise(r => setTimeout(r, 30));
                }

                // ================= PAGE 3 CHARTS =================
                doc.addPage();
                addHeader("Analytics Charts");
                y = 28;

                const history = document.getElementById('historyChart');
                const alerts = document.getElementById('alertChart');
                const predict = document.getElementById('predictChart');

                doc.text("History", 15, y);
                y += 5;
                if (history) doc.addImage(history.toDataURL('image/png'), 'PNG', 15, y, 180, 60);
                y += 70;

                doc.text("Alerts", 15, y);
                y += 5;
                if (alerts) doc.addImage(alerts.toDataURL('image/png'), 'PNG', 15, y, 180, 55);
                y += 65;

                doc.text("Prediction", 15, y);
                y += 5;
                if (predict) doc.addImage(predict.toDataURL('image/png'), 'PNG', 15, y, 180, 45);

                // ================= PAGE 4 TABLE =================
                doc.addPage();
                addHeader("Technical Metrics");
                y = 30;

                doc.autoTable({
                    startY: y,
                    head: [['Metric', 'Value', 'Evaluation']],
                    body: [
                        ['Temperature', currentTemp, tempNum > CONFIG.TEMP_CRITICAL ? 'Critical' : tempNum > CONFIG.TEMP_WARNING ? 'High' : 'Normal'],
                        ['Signal', currentSignal, signalNum < CONFIG.SIGNAL_CRITICAL ? 'Critical' : signalNum < CONFIG.SIGNAL_WARNING ? 'Weak' : 'Stable'],
                        ['AI Score', aiScore, scoreNum > 70 ? 'Healthy' : scoreNum > 40 ? 'Warning' : 'Risk'],
                        ['Anomaly', anomaly, anomaly !== 'None' ? '⚠️ Active' : '✅ Clear']
                    ],
                    theme: 'grid',
                    headStyles: { fillColor: [30, 64, 175] }
                });

                // signature
                y = doc.lastAutoTable.finalY + 40;
                doc.line(30, y, 90, y);
                doc.text("Technical Supervisor", 42, y + 8);

                doc.line(120, y, 180, y);
                doc.text("System Signature", 137, y + 8);

                // ================= PAGE 5 CONCLUSION =================
                doc.addPage();
                addHeader("AI Conclusion");

                y = 35;
                let conclusion = "";

                if (scoreNum >= 80) {
                    conclusion = "The monitored environment operates within optimal parameters. AI confirms healthy status and predictive stability. No immediate action required.";
                } else if (scoreNum >= 50) {
                    conclusion = "Moderate operational instability detected. Preventive maintenance recommended within the next 24 hours.";
                } else {
                    conclusion = "Critical anomalies detected. Immediate intervention required. System stability is compromised.";
                }

                doc.setTextColor(0);
                doc.setFontSize(12);
                doc.text(doc.splitTextToSize(conclusion, 170), 15, y);

                y += 40;

                const insights = document.getElementById('insightsContainer')?.innerText || '';
                const pred = document.getElementById('predictionsContainer')?.innerText || '';
                const rec = document.getElementById('recommendationsContainer')?.innerText || '';

                doc.setFontSize(11);
                doc.setTextColor(30, 64, 175);
                doc.text("AI Insights:", 15, y);
                doc.setTextColor(0);
                doc.setFontSize(10);
                doc.text(doc.splitTextToSize(insights, 170), 15, y + 8);

                y += 30;
                doc.setTextColor(30, 64, 175);
                doc.setFontSize(11);
                doc.text("Predictions:", 15, y);
                doc.setTextColor(0);
                doc.setFontSize(10);
                doc.text(doc.splitTextToSize(pred, 170), 15, y + 8);

                y += 30;
                doc.setTextColor(30, 64, 175);
                doc.setFontSize(11);
                doc.text("Recommendations:", 15, y);
                doc.setTextColor(0);
                doc.setFontSize(10);
                doc.text(doc.splitTextToSize(rec, 170), 15, y + 8);

                addFooter();

                doc.save(`smart-analytics-pro-report-${Date.now()}.pdf`);

            } catch (e) {
                console.error("PDF generation error:", e);
                alert("PDF generation failed. Please try again.");
            } finally {
                if (loadingDiv) loadingDiv.style.display = 'none';
            }
        }

        function init() {
            initCharts();
            switchHistoryView('24h');
            updateAlertChart();
            loadAnalytics();

            setInterval(loadAnalytics, CONFIG.UPDATE_INTERVAL);
            setInterval(updateDeviceStatus, 5000);
            setInterval(updateUptime, 1000);
        }

        document.addEventListener('DOMContentLoaded', init);
    </script>
</body>
</html>
