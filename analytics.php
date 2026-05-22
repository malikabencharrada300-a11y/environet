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
    <title> Analytics  - IoT Monitoring</title>

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
                        <h1 class="text-3xl font-bold gradient-text">Smart Analytics </h1>
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

        <!-- MAIN CARDS (4 columns) -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">

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

            <!-- DHT11 SENSOR STATUS (SANS HUMIDITY) -->
            <div class="glass-card rounded-xl shadow-lg p-6">
                <div class="flex justify-between mb-4">
                    <h2 class="font-bold text-lg text-gray-800">
                        DHT11 Sensor
                    </h2>
                    <span id="dhtIcon" class="text-3xl float-animation">
                        🌡️
                    </span>
                </div>
                <div class="space-y-4">
                    <!-- SENSOR STATUS -->
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center">
                            <span>📡</span>
                        </div>
                        <div>
                            <span class="text-sm text-gray-500">
                                Sensor State
                            </span>
                            <p>
                                <span id="dhtStatus" class="font-bold text-lg status-online">
                                    Connected
                                </span>
                            </p>
                        </div>
                    </div>
                    <!-- LAST READING -->
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center">
                            <span>⏱️</span>
                        </div>
                        <div>
                            <span class="text-sm text-gray-500">
                                Last Reading
                            </span>
                            <p>
                                <span id="dhtLastReading" class="font-mono text-sm font-bold">
                                    --:--:--
                                </span>
                            </p>
                        </div>
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
    UPDATE_INTERVAL: 30000,
    OFFLINE_THRESHOLD: 30000,

    // TEMPERATURE LEVELS
    TEMP_NORMAL_MIN: 18,
    TEMP_NORMAL_MAX: 24,

    TEMP_WARNING_MIN: 24,
    TEMP_WARNING_MAX: 28,

    TEMP_CRITICAL: 28,

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

function safeNum(v) {
    const n = parseFloat(v);
    return isNaN(n) ? 0 : n;
}

// ============================================
// INITIALIZE CHARTS
// ============================================

function initCharts() {

    // TEMP MINI CHART

    state.charts.temp = new Chart(
        document.getElementById('tempTrendChart'),
        {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    data: [],
                    borderColor: '#f97316',
                    backgroundColor: 'rgba(249,115,22,0.1)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2.5,
                    pointRadius: 3,
                    pointBackgroundColor: '#f97316'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        }
    );

    // SIGNAL MINI CHART

    state.charts.signal = new Chart(
        document.getElementById('signalTrendChart'),
        {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    data: [],
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59,130,246,0.1)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2.5,
                    pointRadius: 3,
                    pointBackgroundColor: '#3b82f6'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        }
    );

    // HISTORY CHART

    state.charts.history = new Chart(
        document.getElementById('historyChart'),
        {
            type: 'line',
            data: {
                labels: [],
                datasets: [
                    {
                        label: 'Temperature °C',
                        data: [],
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245,158,11,0.1)',
                        yAxisID: 'y',
                        tension: 0.4,
                        fill: true,
                        borderWidth: 3,
                        pointRadius: 3
                    },
                    {
                        label: 'Signal %',
                        data: [],
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59,130,246,0.1)',
                        yAxisID: 'y1',
                        tension: 0.4,
                        fill: true,
                        borderWidth: 3,
                        pointRadius: 3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    zoom: {
                        zoom: {
                            wheel: { enabled: true },
                            pinch: { enabled: true },
                            mode: 'x'
                        },
                        pan: {
                            enabled: true,
                            mode: 'x'
                        }
                    }
                },
                scales: {
                    y: {
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Temperature (°C)',
                            color: '#F59E0B'
                        },
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        }
                    },
                    y1: {
                        position: 'right',
                        grid: {
                            drawOnChartArea: false
                        },
                        title: {
                            display: true,
                            text: 'Signal (%)',
                            color: '#3B82F6'
                        }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        }
    );

    // ALERT CHART

    state.charts.alert = new Chart(
        document.getElementById('alertChart'),
        {
            type: 'line',
            data: {
                labels: [],
                datasets: [
                    {
                        label: '🌡️ Temperature Alerts',
                        data: [],
                        borderColor: '#EF4444',
                        backgroundColor: 'rgba(239,68,68,0.08)',
                        fill: true,
                        tension: 0.4,
                        borderWidth: 3,
                        pointRadius: 5,
                        pointBackgroundColor: '#EF4444'
                    },
                    {
                        label: '📶 Signal Alerts',
                        data: [],
                        borderColor: '#F59E0B',
                        backgroundColor: 'rgba(245,158,11,0.08)',
                        fill: true,
                        tension: 0.4,
                        borderWidth: 3,
                        pointRadius: 5,
                        pointBackgroundColor: '#F59E0B'
                    },
                    {
                        label: '🔌 Connection Alerts',
                        data: [],
                        borderColor: '#3B82F6',
                        backgroundColor: 'rgba(59,130,246,0.08)',
                        fill: true,
                        tension: 0.4,
                        borderWidth: 3,
                        pointRadius: 5,
                        pointBackgroundColor: '#3B82F6'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        }
    );

    // PREDICTION CHART

    state.charts.predict = new Chart(
        document.getElementById('predictChart'),
        {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: '24h Prediction',
                    data: [],
                    borderColor: '#8b5cf6',
                    backgroundColor: 'rgba(139,92,246,0.1)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 3,
                    pointRadius: 4,
                    borderDash: [8,4]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        }
    );

    updateAlertChartData();
}

// ============================================
// ALERT CHART
// ============================================

async function updateAlertChartData() {

    try {

        const response = await fetch(
            `get_alerts_chart.php`
        );

        const result = await response.json();

        if (!result.success) return;

        const rows = result.alerts;

        const labels = [];
        const tempData = [];
        const signalData = [];
        const connData = [];

        rows.forEach(row => {

            labels.push(row.day);

            tempData.push(
                parseInt(row.temperature || 0)
            );

            signalData.push(
                parseInt(row.signal || 0)
            );

            connData.push(
                parseInt(row.connection || 0)
            );
        });

        state.charts.alert.data.labels = labels;

        state.charts.alert.data.datasets[0].data =
            tempData;

        state.charts.alert.data.datasets[1].data =
            signalData;

        state.charts.alert.data.datasets[2].data =
            connData;

        state.charts.alert.update();

        const totalTemp =
            tempData.reduce((a,b)=>a+b,0);

        const totalSignal =
            signalData.reduce((a,b)=>a+b,0);

        const totalConn =
            connData.reduce((a,b)=>a+b,0);

        document.getElementById('tempAlerts')
            .textContent = totalTemp;

        document.getElementById('signalAlerts')
            .textContent = totalSignal;

        document.getElementById('connectionAlerts')
            .textContent = totalConn;

        document.getElementById('todayAlerts')
            .textContent =
            totalTemp +
            totalSignal +
            totalConn;

        calculateAIScore([
            totalTemp,
            totalSignal,
            totalConn
        ]);

    } catch (e) {

        console.error(
            "Alert Chart Error:",
            e
        );
    }
}

// ============================================
// AI SCORE
// ============================================

function calculateAIScore(arr) {

    const total =
        arr.reduce((a, b) => a + b, 0);

    const score =
        Math.max(
            0,
            Math.min(100, 100 - total * 2)
        );

    document.getElementById('aiScore')
        .textContent = score + '%';

    let status = 'Excellent';
    let cls = 'text-green-600';

    if (score < 50) {

        status = 'Critical';
        cls = 'text-red-600';

    } else if (score < 70) {

        status = 'Warning';
        cls = 'text-orange-600';

    } else if (score < 90) {

        status = 'Good';
        cls = 'text-blue-600';
    }

    document.getElementById('aiHealth')
        .textContent = status;

    document.getElementById('aiHealth')
        .className =
        `text-2xl font-bold ${cls}`;
}

// ============================================
// MINI CHART UPDATE
// ============================================

function pushMini(chart, value) {

    const now =
        new Date().toLocaleTimeString(
            'en-US',
            {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false
            }
        );

    chart.data.labels.push(now);

    chart.data.datasets[0].data.push(value);

    if (
        chart.data.labels.length >
        CONFIG.MAX_DATA_POINTS
    ) {
        chart.data.labels.shift();
        chart.data.datasets[0].data.shift();
    }

    chart.update('none');
}

// ============================================
// HISTORY VIEW
// ============================================

async function switchHistoryView(period) {

    state.currentPeriod = period;

    document.querySelectorAll('[id^="btn"]')
        .forEach(b => {

            b.classList.remove(
                'bg-blue-600',
                'text-white',
                'shadow-md'
            );
        });

    document.getElementById(`btn${period}`)
        .classList.add(
            'bg-blue-600',
            'text-white',
            'shadow-md'
        );

    try {

        const r = await fetch(
            `get_history.php?period=${period}&user_id=<?= $user_id ?>`
        );

        const j = await r.json();

        if (!j.success || !j.history) return;

        const rows = j.history.reverse();

        const labels = rows.map(x => {

            const d = new Date(x.timestamp);

            if (period === '7d') {

                return d.toLocaleDateString(
                    'en-US',
                    {
                        weekday: 'short',
                        day: 'numeric'
                    }
                );
            }

            if (period === '30d') {

                return d.toLocaleDateString(
                    'en-US',
                    {
                        month: 'short',
                        day: 'numeric'
                    }
                );
            }

            return d.toLocaleTimeString(
                'en-US',
                {
                    hour: '2-digit',
                    minute: '2-digit'
                }
            );
        });

        const temp =
            rows.map(x =>
                safeNum(x.temperature)
            );

        const signal =
            rows.map(x =>
                safeNum(x.signal_strength)
            );

        state.charts.history.data.labels =
            labels;

        state.charts.history.data.datasets[0].data =
            temp;

        state.charts.history.data.datasets[1].data =
            signal;

        state.charts.history.update();

        if (temp.length) {

            document.getElementById('historyMin')
                .textContent =
                Math.min(...temp).toFixed(1) + '°C';

            document.getElementById('historyMax')
                .textContent =
                Math.max(...temp).toFixed(1) + '°C';

            document.getElementById('historyAvg')
                .textContent =
                (
                    temp.reduce((a,b)=>a+b,0)
                    / temp.length
                ).toFixed(1) + '°C';

            document.getElementById('dataPoints')
                .textContent =
                temp.length;
        }

        generatePrediction(temp);

    } catch (e) {

        console.error(
            "History error:",
            e
        );
    }
}

// ============================================
// PREDICTION
// ============================================

function generatePrediction(history) {

    if (history.length < 5) return;

    const recent = history.slice(-10);

    const avg =
        recent.reduce((a,b)=>a+b,0)
        / recent.length;

    const trend =
        recent[recent.length-1]
        - recent[0];

    const labels = [];
    const values = [];

    for (let i=1; i<=24; i++) {

        labels.push(`+${i}h`);

        values.push(
            parseFloat(
                (
                    avg +
                    (trend/recent.length)*i +
                    (Math.random()*0.5-0.25)
                ).toFixed(2)
            )
        );
    }

    state.charts.predict.data.labels =
        labels;

    state.charts.predict.data.datasets[0].data =
        values;

    state.charts.predict.update();
}

// ============================================
// ANOMALY
// ============================================

function detectAnomaly(temp, signal) {

    let anomaly = 'None';
    let cls = 'text-green-600';

    if (
        temp > CONFIG.TEMP_CRITICAL ||
        signal < 20
    ) {

        anomaly = 'Critical';
        cls = 'text-red-600';

    } else if (
        temp >= CONFIG.TEMP_WARNING_MIN ||
        signal < 40
    ) {

        anomaly = 'Warning';
        cls = 'text-orange-600';
    }

    document.getElementById('anomalyText')
        .textContent = anomaly;

    document.getElementById('anomalyText')
        .className =
        `text-2xl font-bold ${cls}`;
}

// ============================================
// TEMPERATURE STATUS
// ============================================

function analyzeTemp(temp) {

    document.getElementById('currentTemp')
        .textContent =
        temp.toFixed(1) + '°C';

    let status = 'Normal';
    let color =
        'bg-green-100 text-green-700';

    let icon = '✅';

    // NORMAL 18-24

    if (
        temp >= CONFIG.TEMP_NORMAL_MIN &&
        temp < CONFIG.TEMP_NORMAL_MAX
    ) {

        status = 'Normal';
        color =
            'bg-green-100 text-green-700';

        icon = '✅';
    }

    // WARNING 24-28

    else if (
        temp >= CONFIG.TEMP_WARNING_MIN &&
        temp < CONFIG.TEMP_WARNING_MAX
    ) {

        status = 'Warning';
        color =
            'bg-orange-100 text-orange-700';

        icon = '⚠️';
    }

    // CRITICAL >28

    else if (
        temp >= CONFIG.TEMP_CRITICAL
    ) {

        status = 'Critical';
        color =
            'bg-red-100 text-red-700';

        icon = '🔥';
    }

    // LOW TEMP

    else {

        status = 'Low';
        color =
            'bg-blue-100 text-blue-700';

        icon = '❄️';
    }

    document.getElementById('tempTrend')
        .textContent = status;

    document.getElementById('tempTrend')
        .className =
        `font-bold px-3 py-1 rounded-full text-sm ${color}`;

    document.getElementById('tempIcon')
        .textContent = icon;

    pushMini(
        state.charts.temp,
        temp
    );
}

// ============================================
// SIGNAL STATUS
// ============================================

function analyzeSignal(signal) {

    document.getElementById('currentSignal')
        .textContent =
        signal + '%';

    let s = 'Excellent';
    let c =
        'bg-green-100 text-green-700';

    if (signal < CONFIG.SIGNAL_CRITICAL) {

        s = 'Critical';

        c =
        'bg-red-100 text-red-700';

    } else if (
        signal < CONFIG.SIGNAL_WARNING
    ) {

        s = 'Weak';

        c =
        'bg-orange-100 text-orange-700';
    }

    document.getElementById('signalTrend')
        .textContent = s;

    document.getElementById('signalTrend')
        .className =
        `font-bold px-3 py-1 rounded-full text-sm ${c}`;

    pushMini(
        state.charts.signal,
        signal
    );
}

// ============================================
// OFFLINE UI
// ============================================

function setOfflineUI() {

    // DEVICE STATUS

    document.getElementById('deviceStatus')
        .textContent = 'Offline';

    document.getElementById('deviceStatus')
        .className =
        'font-bold text-lg status-offline';

    // DHT11 STATUS

    document.getElementById('dhtStatus')
        .textContent = 'Disconnected';

    document.getElementById('dhtStatus')
        .className =
        'font-bold text-lg status-offline';

    document.getElementById('dhtIcon')
        .textContent = '🔴';

    // LAST SEEN

    document.getElementById('lastSeen')
        .textContent = '--:--:--';

    document.getElementById('dhtLastReading')
        .textContent = '--:--:--';

    document.getElementById('uptime')
        .textContent = '--';

    // TEMPERATURE

    document.getElementById('currentTemp')
        .textContent = '--°C';

    document.getElementById('tempTrend')
        .textContent = 'Offline';

    document.getElementById('tempTrend')
        .className =
        'font-bold px-3 py-1 rounded-full text-sm bg-red-100 text-red-700';

    document.getElementById('tempIcon')
        .textContent = '🔴';

    // SIGNAL

    document.getElementById('currentSignal')
        .textContent = '--%';

    document.getElementById('signalTrend')
        .textContent = 'Offline';

    document.getElementById('signalTrend')
        .className =
        'font-bold px-3 py-1 rounded-full text-sm bg-red-100 text-red-700';

    document.getElementById('signalIcon')
        .textContent = '🔴';

    // ANOMALY

    document.getElementById('anomalyText')
        .textContent = 'Offline';

    document.getElementById('anomalyText')
        .className =
        'text-2xl font-bold text-red-600';
}

// ============================================
// LOAD ANALYTICS
// ============================================

async function loadAnalytics() {

    try {

        const r = await fetch(
            `get_latest_data.php?user_id=<?= $user_id ?>`
        );

        const j = await r.json();

        if (!j.success || !j.data) {

            setOfflineUI();

            return;
        }

        const createdAt =
            new Date(
                j.data.created_at
            ).getTime();

        const now = Date.now();

        const diff =
            now - createdAt;

        // OFFLINE

        if (
            diff > CONFIG.OFFLINE_THRESHOLD
        ) {

            setOfflineUI();

            return;
        }

        // ONLINE

        state.lastUpdate = createdAt;
        // DEVICE ONLINE

document.getElementById('deviceStatus')
    .textContent = 'Online';

document.getElementById('deviceStatus')
    .className =
    'font-bold text-lg status-online';

// DHT11 ONLINE

document.getElementById('dhtStatus')
    .textContent = 'Connected';

document.getElementById('dhtStatus')
    .className =
    'font-bold text-lg status-online';

document.getElementById('dhtIcon')
    .textContent = '🌡️';

// LAST ACTIVITY

const lastTime =
    new Date(createdAt);

document.getElementById('lastSeen')
    .textContent =
    lastTime.toLocaleTimeString();

document.getElementById('dhtLastReading')
    .textContent =
    lastTime.toLocaleTimeString();

// UPTIME

const uptimeMinutes =
    Math.floor(diff / 60000);

document.getElementById('uptime')
    .textContent =
    uptimeMinutes + ' min';

        const temp =
            safeNum(j.data.temperature);

        const signal =
            safeNum(j.data.signal_strength);

        analyzeTemp(temp);

        analyzeSignal(signal);

        detectAnomaly(temp, signal);

        state.temperatureHistory.push(temp);

        state.signalHistory.push(signal);

        if (
            state.temperatureHistory.length > 20
        ) {
            state.temperatureHistory.shift();
        }

        if (
            state.signalHistory.length > 20
        ) {
            state.signalHistory.shift();
        }

        updateAlertChartData();

    } catch (e) {

        console.error(
            "Analytics Error:",
            e
        );

        setOfflineUI();
    }
}

// ============================================
// PDF REPORT
// ============================================

async function generatePDFReport() {

    const ld =
        document.getElementById('pdfLoading');

    if (ld) {
        ld.style.display = 'block';
    }

    try {

        const { jsPDF } = window.jspdf;

        const doc =
            new jsPDF('p', 'mm', 'a4');

        const W = 210;
        const H = 297;

        const username =
            "<?= htmlspecialchars($username) ?>";

        const currentTemp =
            document.getElementById(
                'currentTemp'
            ).textContent;

        const currentSignal =
            document.getElementById(
                'currentSignal'
            ).textContent;

        const aiScore =
            document.getElementById(
                'aiScore'
            ).textContent;

        const anomaly =
            document.getElementById(
                'anomalyText'
            ).textContent;

        // LOGO

        const logo = new Image();

        logo.src = 'logo.png';

        await new Promise(resolve => {
            logo.onload = resolve;
        });

        // COVER PAGE

        doc.setFillColor(15,23,42);

        doc.rect(0,0,W,H,'F');

        // ADD LOGO

        doc.addImage(
            logo,
            'PNG',
            75,
            20,
            60,
            60
        );

        doc.setTextColor(255,255,255);

        doc.setFontSize(28);

        doc.text(
            "ENVIRONET",
            58,
            95
        );

        doc.setFontSize(18);

        doc.text(
            "SMART ANALYTICS REPORT",
            28,
            115
        );

        doc.setFontSize(12);

        doc.text(
            `Generated for: ${username}`,
            35,
            145
        );

        doc.text(
            `Date: ${new Date().toLocaleString()}`,
            35,
            155
        );

        // DASHBOARD PAGE

        doc.addPage();

        doc.setTextColor(0);

        doc.setFontSize(18);

        doc.text(
            "Executive Summary",
            20,
            25
        );

        doc.autoTable({

            startY: 40,

            head: [[
                'Metric',
                'Value',
                'Status'
            ]],

            body: [

                [
                    'Temperature',
                    currentTemp,

                    parseFloat(currentTemp)
                    >= CONFIG.TEMP_CRITICAL

                    ? 'Critical'

                    :

                    parseFloat(currentTemp)
                    >= CONFIG.TEMP_WARNING_MIN

                    ? 'Warning'

                    : 'Normal'
                ],

                [
                    'Signal',
                    currentSignal,

                    parseFloat(currentSignal)
                    < CONFIG.SIGNAL_CRITICAL

                    ? 'Critical'

                    :

                    parseFloat(currentSignal)
                    < CONFIG.SIGNAL_WARNING

                    ? 'Weak'

                    : 'Excellent'
                ],

                [
                    'AI Score',
                    aiScore,
                    'AI Health'
                ],

                [
                    'Anomaly',
                    anomaly,
                    anomaly
                ]
            ],

            theme: 'grid',

            headStyles: {
                fillColor: [30,64,175]
            }
        });

        // CHARTS PAGE

        doc.addPage();

        doc.setFontSize(18);

        doc.text(
            "Analytics Charts",
            20,
            20
        );

        const historyCanvas =
            document.getElementById(
                'historyChart'
            );

        const alertCanvas =
            document.getElementById(
                'alertChart'
            );

        if (historyCanvas) {

            doc.addImage(
                historyCanvas.toDataURL('image/png'),
                'PNG',
                15,
                35,
                180,
                70
            );
        }

        if (alertCanvas) {

            doc.addImage(
                alertCanvas.toDataURL('image/png'),
                'PNG',
                15,
                130,
                180,
                70
            );
        }

        // CONCLUSION PAGE

        doc.addPage();

        doc.setFontSize(18);

        doc.text(
            "AI Conclusion",
            20,
            25
        );

        let conclusion = '';

        const tempValue =
            parseFloat(currentTemp);

        if (
            tempValue >= CONFIG.TEMP_CRITICAL
        ) {

            conclusion =
            'Critical temperature detected. Immediate intervention recommended.';

        } else if (
            tempValue >= CONFIG.TEMP_WARNING_MIN
        ) {

            conclusion =
            'Warning temperature range detected. Monitoring recommended.';

        } else {

            conclusion =
            'System operating normally within safe parameters.';
        }

        doc.setFontSize(12);

        doc.text(
            doc.splitTextToSize(
                conclusion,
                170
            ),
            20,
            50
        );

        // FOOTER

        const pages =
            doc.internal.getNumberOfPages();

        for (let i = 1; i <= pages; i++) {

            doc.setPage(i);

            doc.setFontSize(8);

            doc.setTextColor(120);

            doc.text(
                `ENVIRONET • Page ${i}/${pages}`,
                15,
                292
            );
        }

        doc.save(
            `analytics-report-${Date.now()}.pdf`
        );

    } catch (e) {

        console.error(
            "PDF Error:",
            e
        );

        alert(
            "PDF generation failed"
        );

    } finally {

        if (ld) {

            ld.style.display = 'none';
        }
    }
}
// ============================================
// INIT
// ============================================

function init() {

    initCharts();

    switchHistoryView('24h');

    loadAnalytics();

    setInterval(
        loadAnalytics,
        CONFIG.UPDATE_INTERVAL
    );
}

document.addEventListener(
    'DOMContentLoaded',
    init
);
</script>
</body>
</html>
