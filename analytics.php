<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
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
    <title>Smart Analytics - IoT Monitoring</title>

    <!-- Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        .status-online { color: #10B981; }
        .status-offline { color: #EF4444; }
        .status-warning { color: #F59E0B; }
        .status-critical { color: #DC2626; }
        .status-good { color: #10B981; }
        
        .chart-container {
            position: relative;
            width: 100%;
            height: 200px;
        }
        
        .pdf-loading {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
            z-index: 9999;
        }
        
        .smooth-hover {
            transition: all .3s ease;
        }
        
        .smooth-hover:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 18px rgba(0,0,0,0.08);
        }
        
        .insight-item {
            padding: 8px 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        .insight-item:last-child {
            border-bottom: none;
        }
        
        .btn-active {
            background-color: #3b82f6 !important;
            color: white !important;
        }
        
        .alert-stat {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .alert-stat.critical { background: #FEE2E2; color: #DC2626; }
        .alert-stat.warning { background: #FEF3C7; color: #D97706; }
        .alert-stat.info { background: #E0F2FE; color: #0284C7; }
    </style>
</head>

<body class="bg-gradient-to-br from-gray-50 to-blue-50 min-h-screen">

    <div id="pdfLoading" class="pdf-loading">
        <div class="text-center">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-purple-600 mx-auto mb-4"></div>
            <p class="text-gray-700 font-semibold">Generating PDF report...</p>
        </div>
    </div>

    <div class="container mx-auto p-6 max-w-7xl">

        <!-- Top Bar -->
        <div class="bg-white shadow-sm rounded-2xl px-6 py-4 mb-8 flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center gap-6 flex-wrap">
                <a href="dashboard.php"
                   class="flex items-center gap-2 bg-blue-800 hover:bg-blue-900 text-white px-5 py-2 rounded-full font-semibold transition">
                    ← Dashboard
                </a>
                <div class="flex items-center gap-2">
                    <span class="text-xl">📊</span>
                    <h1 class="text-3xl font-bold text-gray-800">Smart Analytics</h1>
                </div>
            </div>

            <div class="flex items-center gap-5 flex-wrap">
                <button onclick="generatePDFReport()"
                        class="bg-purple-600 hover:bg-purple-700 text-white px-5 py-2 rounded-lg shadow font-semibold transition">
                    📄 Generate PDF Report
                </button>
                <div class="flex items-center gap-2 text-sm font-semibold text-green-600">
                    <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                    <span id="liveStatusText">Real-time</span>
                </div>
                <div class="flex items-center gap-3 bg-gray-100 px-4 py-2 rounded-full shadow-sm">
                    <span class="text-sm font-semibold text-blue-900">
                        <?php echo htmlspecialchars($username); ?>
                    </span>
                    <div class="w-9 h-9 rounded-full bg-blue-900 text-white flex items-center justify-center font-bold">
                        <?php echo strtoupper(substr($username, 0, 1)); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-white rounded-xl shadow-lg p-6 smooth-hover">
                <div class="flex justify-between mb-4">
                    <h2 class="font-bold text-lg text-gray-800">Device Status</h2>
                    <span id="deviceStatusIcon" class="text-2xl">📡</span>
                </div>
                <div class="space-y-3">
                    <div class="flex items-center space-x-2">
                        <span class="text-gray-600">Status:</span>
                        <span id="deviceStatus" class="font-semibold text-gray-400">Checking...</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="text-gray-600">Last Activity:</span>
                        <span id="lastSeen" class="font-mono text-sm">--:--:--</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="text-gray-600">Uptime:</span>
                        <span id="uptime" class="font-mono text-sm">--</span>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6 smooth-hover">
                <div class="flex justify-between mb-4">
                    <h2 class="font-bold text-lg text-gray-800">Temperature Analysis</h2>
                    <span id="tempIcon" class="text-2xl">🌡️</span>
                </div>
                <div class="mb-4">
                    <p class="text-gray-600">Trend: <span id="tempTrend" class="font-semibold">Analyzing...</span></p>
                    <p class="text-gray-600 mt-1">Current: <span id="currentTemp" class="font-bold text-xl">--°C</span></p>
                </div>
                <div class="chart-container">
                    <canvas id="tempTrendChart"></canvas>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6 smooth-hover">
                <div class="flex justify-between mb-4">
                    <h2 class="font-bold text-lg text-gray-800">Network Analysis</h2>
                    <span id="signalIcon" class="text-2xl">📶</span>
                </div>
                <div class="mb-4">
                    <p class="text-gray-600">Signal: <span id="signalTrend" class="font-semibold">Analyzing...</span></p>
                    <p class="text-gray-600 mt-1">Strength: <span id="currentSignal" class="font-bold text-xl">--%</span></p>
                </div>
                <div class="chart-container">
                    <canvas id="signalTrendChart"></canvas>
                </div>
            </div>
        </div>

        <!-- AI Section -->
        <div class="bg-gradient-to-r from-purple-50 to-blue-50 rounded-xl shadow-lg p-6 mb-6">
            <div class="flex justify-between mb-6 flex-wrap gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">Artificial Intelligence</h2>
                    <p class="text-sm text-gray-600">Predictive analysis & recommendations</p>
                </div>
                <span id="aiStatus" class="text-sm font-semibold text-green-600">● Active</span>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white rounded-lg p-4 shadow">
                    <h3 class="font-bold text-purple-700 mb-3">📊 Insights</h3>
                    <div id="insightsContainer" class="text-sm"></div>
                </div>
                <div class="bg-white rounded-lg p-4 shadow">
                    <h3 class="font-bold text-blue-700 mb-3">🔮 Predictions</h3>
                    <div id="predictionsContainer" class="text-sm"></div>
                </div>
                <div class="bg-white rounded-lg p-4 shadow">
                    <h3 class="font-bold text-green-700 mb-3">💡 Recommendations</h3>
                    <div id="recommendationsContainer" class="text-sm"></div>
                </div>
            </div>
            <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="p-3 bg-white rounded-lg">
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-semibold">🤖 AI Health Score:</span>
                        <span id="aiScore" class="text-2xl font-bold text-purple-600">--</span>
                    </div>
                </div>
                <div class="p-3 bg-white rounded-lg">
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-semibold">⚠️ Anomaly Detection:</span>
                        <span id="anomalyText" class="text-sm font-medium px-3 py-1 rounded-full">Normal</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- History Chart -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex justify-between mb-4 flex-wrap gap-2">
                    <h3 class="font-bold text-lg">📈 History Chart</h3>
                    <div class="flex space-x-2">
                        <button onclick="switchHistoryView('24h')" class="history-view-btn px-3 py-1 text-sm rounded bg-blue-100 hover:bg-blue-200 transition">24h</button>
                        <button onclick="switchHistoryView('7d')" class="history-view-btn px-3 py-1 text-sm rounded bg-gray-100 hover:bg-gray-200 transition">7d</button>
                        <button onclick="switchHistoryView('30d')" class="history-view-btn px-3 py-1 text-sm rounded bg-gray-100 hover:bg-gray-200 transition">30d</button>
                    </div>
                </div>
                <div class="chart-container" style="height: 300px;">
                    <canvas id="historyChart"></canvas>
                </div>
                <div class="mt-4 flex justify-between text-sm text-gray-600">
                    <span>Min: <span id="historyMin">--</span></span>
                    <span>Max: <span id="historyMax">--</span></span>
                    <span>Avg: <span id="historyAvg">--</span></span>
                </div>
                <div class="mt-6">
                    <h4 class="font-semibold text-purple-700 mb-2">🔮 24h Forecast</h4>
                    <div class="chart-container" style="height: 200px;">
                        <canvas id="predictChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Alert Chart -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex justify-between mb-4 flex-wrap gap-2">
                    <h3 class="font-bold text-lg">⚠️ Alert Chart</h3>
                    <select id="alertTypeFilter" onchange="filterAlerts()" class="px-3 py-1 text-sm rounded border border-gray-300">
                        <option value="all">📊 All Alerts</option>
                        <option value="temperature">🌡️ Temperature</option>
                        <option value="signal">📶 Signal</option>
                        <option value="connection">🔌 Connection</option>
                    </select>
                </div>
                <div class="chart-container" style="height: 300px;">
                    <canvas id="alertChart"></canvas>
                </div>
                <div class="mt-4 flex justify-between items-center">
                    <p class="text-sm text-gray-600">
                        📅 Today's alerts: <span id="todayAlerts" class="font-bold text-red-600 text-lg">0</span>
                    </p>
                    <div class="flex gap-2">
                        <span class="alert-stat critical">🔴 Critical</span>
                        <span class="alert-stat warning">🟡 Warning</span>
                        <span class="alert-stat info">🔵 Info</span>
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
        SIGNAL_WARNING: 50
    };

    const state = {
        charts: {},
        lastUpdate: Date.now(),
        currentPeriod: '24h',
        tempHistory: [],
        signalHistory: [],
        alertChartInitialized: false
    };

    function safeNum(v) {
        const n = parseFloat(v);
        return isNaN(n) ? 0 : n;
    }

    function initCharts() {
        console.log('Initializing charts...');
        
        // Temperature chart
        const tempCtx = document.getElementById('tempTrendChart');
        if (tempCtx) {
            state.charts.temp = new Chart(tempCtx.getContext('2d'), {
                type: 'line',
                data: { labels: [], datasets: [{ data: [], borderColor: '#f97316', backgroundColor: 'rgba(249,115,22,0.1)', fill: true, tension: 0.4 }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
            });
        }

        // Signal chart
        const signalCtx = document.getElementById('signalTrendChart');
        if (signalCtx) {
            state.charts.signal = new Chart(signalCtx.getContext('2d'), {
                type: 'line',
                data: { labels: [], datasets: [{ data: [], borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,0.1)', fill: true, tension: 0.4 }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
            });
        }

        // History chart
        const historyCtx = document.getElementById('historyChart');
        if (historyCtx) {
            state.charts.history = new Chart(historyCtx.getContext('2d'), {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [
                        { label: 'Temperature °C', data: [], borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,0.1)', yAxisID: 'y', tension: 0.4, fill: true },
                        { label: 'Signal %', data: [], borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,0.1)', yAxisID: 'y1', tension: 0.4, fill: true }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    scales: { 
                        y: { position: 'left', title: { display: true, text: 'Temperature (°C)' } }, 
                        y1: { position: 'right', title: { display: true, text: 'Signal (%)' }, grid: { drawOnChartArea: false }, min: 0, max: 100 } 
                    }
                }
            });
        }

        // Alert chart - Version corrigée pour PostgreSQL
        const alertCtx = document.getElementById('alertChart');
        if (alertCtx) {
            state.charts.alert = new Chart(alertCtx.getContext('2d'), {
                type: 'bar',
                data: { 
                    labels: [], 
                    datasets: [
                        { label: '🌡️ Temperature Alerts', data: [], backgroundColor: '#dc2626', borderRadius: 8 },
                        { label: '📶 Signal Alerts', data: [], backgroundColor: '#f59e0b', borderRadius: 8 },
                        { label: '🔌 Connection Alerts', data: [], backgroundColor: '#3b82f6', borderRadius: 8 }
                    ] 
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { 
                        legend: { position: 'top' },
                        tooltip: { 
                            callbacks: { 
                                label: function(context) { 
                                    return context.dataset.label + ': ' + context.raw + ' alerts'; 
                                } 
                            } 
                        }
                    },
                    scales: { 
                        y: { 
                            beginAtZero: true, 
                            title: { display: true, text: 'Number of Alerts' }, 
                            stepSize: 1,
                            ticks: { precision: 0 }
                        } 
                    }
                }
            });
            state.alertChartInitialized = true;
            console.log('Alert chart initialized');
        }

        // Prediction chart
        const predictCtx = document.getElementById('predictChart');
        if (predictCtx) {
            state.charts.predict = new Chart(predictCtx.getContext('2d'), {
                type: 'line',
                data: { labels: [], datasets: [{ label: 'Temperature Forecast', data: [], borderColor: '#8b5cf6', backgroundColor: 'rgba(139,92,246,0.1)', fill: true, tension: 0.4 }] },
                options: { responsive: true, maintainAspectRatio: false }
            });
        }
    }

    // Fonction pour mettre à jour le graphique d'alertes - CORRIGÉE
    async function updateAlertChart() {
        console.log('Updating alert chart...');
        
        if (!state.charts.alert) {
            console.error('Alert chart not initialized');
            return;
        }
        
        try {
            const filter = document.getElementById('alertTypeFilter')?.value || 'all';
            console.log('Filter:', filter);
            
            const response = await fetch(`get_alert_chart.php?filter=${filter}&user_id=<?= $user_id ?>`);
            const data = await response.json();
            
            console.log('API Response:', data);

            if (data.success && data.alerts && data.alerts.length > 0) {
                // Extraire les labels (jours)
                const labels = data.alerts.map(x => x.day);
                console.log('Labels:', labels);
                
                if (filter === 'all') {
                    // Données pour les 3 types d'alertes
                    const tempData = data.alerts.map(x => x.temperature || 0);
                    const signalData = data.alerts.map(x => x.signal || 0);
                    const connData = data.alerts.map(x => x.connection || 0);
                    
                    console.log('Temperature data:', tempData);
                    console.log('Signal data:', signalData);
                    console.log('Connection data:', connData);
                    
                    // Mettre à jour le graphique
                    state.charts.alert.data.labels = labels;
                    state.charts.alert.data.datasets[0].data = tempData;
                    state.charts.alert.data.datasets[1].data = signalData;
                    state.charts.alert.data.datasets[2].data = connData;
                    state.charts.alert.update();
                    
                    // Mettre à jour le compteur d'alertes du jour
                    const todaySpan = document.getElementById('todayAlerts');
                    if (todaySpan) {
                        const todayTotal = data.today || 0;
                        todaySpan.innerHTML = `${todayTotal}`;
                    }
                    
                    // Calculer le score IA
                    calculateAIScore(data.total || 0);
                    
                } else {
                    // Filtrer par type spécifique
                    let alertData = [];
                    let alertColor = '#dc2626';
                    let alertLabel = '';
                    
                    if (filter === 'temperature') {
                        alertData = data.alerts.map(x => x.temperature || 0);
                        alertColor = '#dc2626';
                        alertLabel = '🌡️ Temperature Alerts';
                    } else if (filter === 'signal') {
                        alertData = data.alerts.map(x => x.signal || 0);
                        alertColor = '#f59e0b';
                        alertLabel = '📶 Signal Alerts';
                    } else {
                        alertData = data.alerts.map(x => x.connection || 0);
                        alertColor = '#3b82f6';
                        alertLabel = '🔌 Connection Alerts';
                    }
                    
                    state.charts.alert.data.labels = labels;
                    state.charts.alert.data.datasets[0].data = alertData;
                    state.charts.alert.data.datasets[0].label = alertLabel;
                    state.charts.alert.data.datasets[0].backgroundColor = alertColor;
                    state.charts.alert.data.datasets[1].data = [];
                    state.charts.alert.data.datasets[2].data = [];
                    state.charts.alert.update();
                    
                    const todayTotal = alertData[alertData.length - 1] || 0;
                    const todaySpan = document.getElementById('todayAlerts');
                    if (todaySpan) todaySpan.textContent = todayTotal;
                    
                    const totalAlerts = alertData.reduce((a, b) => a + b, 0);
                    calculateAIScore(totalAlerts);
                }
            } else {
                console.log('No data, using zeros');
                // Initialiser avec des zéros
                const emptyLabels = [];
                for (let i = 6; i >= 0; i--) {
                    const d = new Date();
                    d.setDate(d.getDate() - i);
                    emptyLabels.push(d.getDate() + '/' + (d.getMonth() + 1));
                }
                
                state.charts.alert.data.labels = emptyLabels;
                state.charts.alert.data.datasets[0].data = [0, 0, 0, 0, 0, 0, 0];
                state.charts.alert.data.datasets[1].data = [0, 0, 0, 0, 0, 0, 0];
                state.charts.alert.data.datasets[2].data = [0, 0, 0, 0, 0, 0, 0];
                state.charts.alert.update();
                
                const todaySpan = document.getElementById('todayAlerts');
                if (todaySpan) todaySpan.textContent = '0';
                calculateAIScore(0);
            }
        } catch (error) {
            console.error('Error in updateAlertChart:', error);
        }
    }

    function calculateAIScore(totalAlerts) {
        // Calcul du score: 100 - (alertes * 5)
        let score = 100 - (totalAlerts * 5);
        score = Math.max(0, Math.min(100, score));
        
        const aiScoreSpan = document.getElementById('aiScore');
        if (aiScoreSpan) {
            aiScoreSpan.textContent = score + '%';
            
            if (score >= 80) {
                aiScoreSpan.className = 'text-2xl font-bold text-green-600';
            } else if (score >= 50) {
                aiScoreSpan.className = 'text-2xl font-bold text-orange-500';
            } else {
                aiScoreSpan.className = 'text-2xl font-bold text-red-600';
            }
        }
        
        // Mettre à jour l'anomalie detection
        const anomalySpan = document.getElementById('anomalyText');
        if (anomalySpan) {
            if (score >= 80) {
                anomalySpan.textContent = '🟢 Normal operation';
                anomalySpan.className = 'text-sm font-medium px-3 py-1 rounded-full bg-green-100 text-green-700';
            } else if (score >= 50) {
                anomalySpan.textContent = '🟡 Potential anomaly detected';
                anomalySpan.className = 'text-sm font-medium px-3 py-1 rounded-full bg-yellow-100 text-yellow-700';
            } else {
                anomalySpan.textContent = '🔴 Critical anomaly detected';
                anomalySpan.className = 'text-sm font-medium px-3 py-1 rounded-full bg-red-100 text-red-700';
            }
        }
        
        // Mettre à jour le statut IA
        const aiStatusSpan = document.getElementById('aiStatus');
        if (aiStatusSpan) {
            if (score >= 80) {
                aiStatusSpan.innerHTML = '● Active';
                aiStatusSpan.className = 'text-sm font-semibold text-green-600';
            } else if (score >= 50) {
                aiStatusSpan.innerHTML = '● Warning';
                aiStatusSpan.className = 'text-sm font-semibold text-orange-500';
            } else {
                aiStatusSpan.innerHTML = '● Critical';
                aiStatusSpan.className = 'text-sm font-semibold text-red-600';
            }
        }
    }

    function filterAlerts() {
        console.log('Filter changed, reloading alerts...');
        updateAlertChart();
    }

    async function switchHistoryView(period) {
        state.currentPeriod = period;
        
        document.querySelectorAll('.history-view-btn').forEach(btn => {
            btn.classList.remove('bg-blue-100');
            btn.classList.add('bg-gray-100');
        });
        if (event && event.target) {
            event.target.classList.remove('bg-gray-100');
            event.target.classList.add('bg-blue-100');
        }

        try {
            const response = await fetch(`get_history.php?period=${period}&user_id=<?= $user_id ?>`);
            const data = await response.json();

            if (data.success && data.history && data.history.length > 0) {
                const rows = data.history;
                const labels = rows.map(x => {
                    const d = new Date(x.timestamp);
                    return period === '7d' || period === '30d' ? d.toLocaleDateString() : d.toLocaleTimeString();
                });
                const tempData = rows.map(x => safeNum(x.temperature));
                const signalData = rows.map(x => safeNum(x.signal_strength));

                if (state.charts.history) {
                    state.charts.history.data.labels = labels;
                    state.charts.history.data.datasets[0].data = tempData;
                    state.charts.history.data.datasets[1].data = signalData;
                    state.charts.history.update();
                }

                if (tempData.length > 0) {
                    document.getElementById('historyMin').textContent = `${Math.min(...tempData).toFixed(1)}°C / ${Math.min(...signalData)}%`;
                    document.getElementById('historyMax').textContent = `${Math.max(...tempData).toFixed(1)}°C / ${Math.max(...signalData)}%`;
                    document.getElementById('historyAvg').textContent = `${(tempData.reduce((a,b) => a+b, 0) / tempData.length).toFixed(1)}°C / ${(signalData.reduce((a,b) => a+b, 0) / signalData.length).toFixed(0)}%`;
                    generatePrediction(tempData);
                }
            }
        } catch (e) {
            console.error('Error loading history:', e);
        }
    }

    function generatePrediction(history) {
        if (!state.charts.predict || history.length < 5) return;
        
        const recentAvg = history.slice(-6).reduce((a, b) => a + b, 0) / 6;
        const labels = [];
        const values = [];
        
        for (let i = 1; i <= 12; i++) {
            labels.push(`+${i}h`);
            const variation = (Math.sin(i * 0.5) * 1.5) + (Math.random() * 1 - 0.5);
            values.push((recentAvg + variation).toFixed(1));
        }
        
        state.charts.predict.data.labels = labels;
        state.charts.predict.data.datasets[0].data = values;
        state.charts.predict.update();
    }

    function updateInsights(temp, signal) {
        const container = document.getElementById('insightsContainer');
        if (!container) return;
        
        let insights = [];
        
        if (temp > CONFIG.TEMP_CRITICAL) {
            insights.push('<div class="insight-item text-red-600">🔴 Critical temperature: ' + temp.toFixed(1) + '°C</div>');
        } else if (temp > CONFIG.TEMP_WARNING) {
            insights.push('<div class="insight-item text-orange-500">🟡 High temperature: ' + temp.toFixed(1) + '°C</div>');
        } else {
            insights.push('<div class="insight-item text-green-600">🟢 Normal temperature: ' + temp.toFixed(1) + '°C</div>');
        }
        
        if (signal < CONFIG.SIGNAL_CRITICAL) {
            insights.push('<div class="insight-item text-red-600">🔴 Critical signal: ' + signal + '%</div>');
        } else if (signal < CONFIG.SIGNAL_WARNING) {
            insights.push('<div class="insight-item text-orange-500">🟡 Weak signal: ' + signal + '%</div>');
        } else {
            insights.push('<div class="insight-item text-green-600">🟢 Optimal signal: ' + signal + '%</div>');
        }
        
        container.innerHTML = insights.join('');
    }

    function updatePredictions(temp, signal) {
        const container = document.getElementById('predictionsContainer');
        if (!container) return;
        
        let predictions = [];
        
        if (state.tempHistory.length >= 5) {
            const recentAvg = state.tempHistory.slice(-5).reduce((a, b) => a + b, 0) / 5;
            const trend = state.tempHistory[state.tempHistory.length - 1] - state.tempHistory[state.tempHistory.length - 5];
            const predictedTemp = recentAvg + (trend * 0.5);
            predictions.push('<div class="insight-item">🌡️ Expected in 1h: ' + predictedTemp.toFixed(1) + '°C</div>');
        }
        
        if (state.signalHistory.length >= 5) {
            const avgSignal = state.signalHistory.slice(-5).reduce((a, b) => a + b, 0) / 5;
            predictions.push('<div class="insight-item">📶 Signal forecast: ' + avgSignal.toFixed(0) + '%</div>');
        }
        
        const stabilityScore = Math.min(100, Math.max(0, 100 - (Date.now() - state.lastUpdate) / 600));
        predictions.push('<div class="insight-item">🔧 Stability: ' + stabilityScore.toFixed(0) + '%</div>');
        
        container.innerHTML = predictions.join('');
    }

    function updateRecommendations(temp, signal) {
        const container = document.getElementById('recommendationsContainer');
        if (!container) return;
        
        let recommendations = [];
        
        if (temp > CONFIG.TEMP_CRITICAL) {
            recommendations.push('<div class="insight-item text-red-600">🚨 IMMEDIATE: Activate cooling system</div>');
        } else if (temp > CONFIG.TEMP_WARNING) {
            recommendations.push('<div class="insight-item text-orange-500">⚡ Optimize energy consumption</div>');
        } else {
            recommendations.push('<div class="insight-item text-green-600">✅ System running optimally</div>');
        }
        
        if (signal < CONFIG.SIGNAL_CRITICAL) {
            recommendations.push('<div class="insight-item text-red-600">📡 Check antenna connection</div>');
        } else if (signal < CONFIG.SIGNAL_WARNING) {
            recommendations.push('<div class="insight-item text-orange-500">🔄 Optimize antenna position</div>');
        }
        
        container.innerHTML = recommendations.join('');
    }

    function analyzeTemperature(temp) {
        const currentTemp = document.getElementById('currentTemp');
        const tempTrend = document.getElementById('tempTrend');
        const tempIcon = document.getElementById('tempIcon');
        
        if (currentTemp) currentTemp.textContent = temp.toFixed(1) + '°C';
        
        let status = 'Stable';
        let icon = '🌡️';
        if (temp > CONFIG.TEMP_CRITICAL) {
            status = 'Critical';
            icon = '🔥';
        } else if (temp > CONFIG.TEMP_WARNING) {
            status = 'High';
            icon = '🌡️';
        }
        
        if (tempTrend) {
            tempTrend.textContent = status;
            tempTrend.className = `font-semibold ${status === 'Critical' ? 'status-critical' : (status === 'High' ? 'status-warning' : 'status-good')}`;
        }
        if (tempIcon) tempIcon.textContent = icon;
        
        if (state.charts.temp) {
            const now = new Date().toLocaleTimeString();
            state.charts.temp.data.labels.push(now);
            state.charts.temp.data.datasets[0].data.push(temp);
            if (state.charts.temp.data.labels.length > 20) {
                state.charts.temp.data.labels.shift();
                state.charts.temp.data.datasets[0].data.shift();
            }
            state.charts.temp.update();
        }
        
        state.tempHistory.push(temp);
        if (state.tempHistory.length > 50) state.tempHistory.shift();
    }

    function analyzeSignal(signal) {
        const currentSignal = document.getElementById('currentSignal');
        const signalTrend = document.getElementById('signalTrend');
        const signalIcon = document.getElementById('signalIcon');
        
        if (currentSignal) currentSignal.textContent = signal + '%';
        
        let status = 'Good';
        let icon = '📶';
        if (signal < CONFIG.SIGNAL_CRITICAL) {
            status = 'Critical';
            icon = '📡';
        } else if (signal < CONFIG.SIGNAL_WARNING) {
            status = 'Weak';
            icon = '📶';
        }
        
        if (signalTrend) {
            signalTrend.textContent = status;
            signalTrend.className = `font-semibold ${status === 'Critical' ? 'status-critical' : (status === 'Weak' ? 'status-warning' : 'status-good')}`;
        }
        if (signalIcon) signalIcon.textContent = icon;
        
        if (state.charts.signal) {
            const now = new Date().toLocaleTimeString();
            state.charts.signal.data.labels.push(now);
            state.charts.signal.data.datasets[0].data.push(signal);
            if (state.charts.signal.data.labels.length > 20) {
                state.charts.signal.data.labels.shift();
                state.charts.signal.data.datasets[0].data.shift();
            }
            state.charts.signal.update();
        }
        
        state.signalHistory.push(signal);
        if (state.signalHistory.length > 50) state.signalHistory.shift();
    }

    function updateDeviceStatus() {
        const diff = Date.now() - state.lastUpdate;
        const online = diff < CONFIG.OFFLINE_THRESHOLD;
        
        const deviceStatus = document.getElementById('deviceStatus');
        const liveStatus = document.getElementById('liveStatusText');
        const deviceIcon = document.getElementById('deviceStatusIcon');
        
        if (deviceStatus) {
            deviceStatus.textContent = online ? 'Online' : 'Offline';
            deviceStatus.className = online ? 'font-semibold status-online' : 'font-semibold status-offline';
        }
        if (liveStatus) {
            liveStatus.textContent = online ? 'Live' : 'Offline';
            liveStatus.className = online ? 'text-green-600' : 'text-red-600';
        }
        if (deviceIcon) deviceIcon.textContent = online ? '📡' : '🔴';
    }

    function updateUptime() {
        const seconds = Math.floor((Date.now() - state.lastUpdate) / 1000);
        const uptimeSpan = document.getElementById('uptime');
        if (uptimeSpan) uptimeSpan.textContent = seconds + ' sec';
    }

    async function loadAnalytics() {
        try {
            const response = await fetch(`get_latest_data.php?user_id=<?= $user_id ?>`);
            const data = await response.json();

            if (data.success && data.data) {
                state.lastUpdate = Date.now();
                
                const lastSeenSpan = document.getElementById('lastSeen');
                if (lastSeenSpan) lastSeenSpan.textContent = new Date().toLocaleTimeString();

                const temperature = safeNum(data.data.temperature);
                const signal = safeNum(data.data.signal_strength);

                analyzeTemperature(temperature);
                analyzeSignal(signal);
                
                updateInsights(temperature, signal);
                updatePredictions(temperature, signal);
                updateRecommendations(temperature, signal);
                
                await updateAlertChart();
            }
        } catch (error) {
            console.error('Error loading analytics:', error);
        }
    }

    async function generatePDFReport() {
        const loadingDiv = document.getElementById('pdfLoading');
        if (loadingDiv) loadingDiv.style.display = 'block';
        
        try {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            
            doc.setFontSize(20);
            doc.setTextColor(139, 92, 246);
            doc.text('Smart Analytics Report', 20, 20);
            
            doc.setFontSize(12);
            doc.setTextColor(100, 100, 100);
            doc.text(`Generated: ${new Date().toLocaleString()}`, 20, 30);
            doc.text(`User: <?= htmlspecialchars($username) ?>`, 20, 38);
            
            doc.setDrawColor(139, 92, 246);
            doc.line(20, 45, 190, 45);
            
            doc.setFontSize(14);
            doc.setTextColor(0, 0, 0);
            doc.text('Current Status', 20, 58);
            
            const currentTemp = document.getElementById('currentTemp')?.textContent || '--';
            const currentSignal = document.getElementById('currentSignal')?.textContent || '--';
            const deviceStatus = document.getElementById('deviceStatus')?.textContent || '--';
            const aiScore = document.getElementById('aiScore')?.textContent || '--';
            
            doc.setFontSize(11);
            doc.text(`Device Status: ${deviceStatus}`, 25, 68);
            doc.text(`Temperature: ${currentTemp}`, 25, 76);
            doc.text(`Signal: ${currentSignal}`, 25, 84);
            doc.text(`AI Health Score: ${aiScore}`, 25, 92);
            
            const historyChart = document.getElementById('historyChart');
            if (historyChart) {
                const imgData = historyChart.toDataURL('image/png');
                doc.addImage(imgData, 'PNG', 20, 105, 170, 70);
            }
            
            doc.save(`smart-analytics-${new Date().toISOString().split('T')[0]}.pdf`);
            
        } catch (error) {
            console.error('PDF Error:', error);
            alert('Error generating PDF report');
        } finally {
            if (loadingDiv) loadingDiv.style.display = 'none';
        }
    }

    function init() {
        console.log('Initializing application...');
        initCharts();
        
        setTimeout(() => {
            loadAnalytics();
            switchHistoryView('24h');
            updateAlertChart();
        }, 500);
        
        setInterval(loadAnalytics, CONFIG.UPDATE_INTERVAL);
        setInterval(updateDeviceStatus, CONFIG.UPDATE_INTERVAL);
        setInterval(updateUptime, 1000);
        
        updateDeviceStatus();
    }

    document.addEventListener('DOMContentLoaded', init);
</script>
</body>
</html>
