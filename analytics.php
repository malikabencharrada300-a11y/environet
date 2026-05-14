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
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Analytics - Monitoring IoT</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .status-online { color: #10B981; }
        .status-offline { color: #EF4444; }
        .status-critical { color: #DC2626; }
        .status-warning { color: #F59E0B; }
        .status-good { color: #10B981; }
        
        .chart-container {
            position: relative;
            height: 200px;
            width: 100%;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .pulse-animation {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        .btn-pdf:hover {
            transform: translateY(-2px);
        }
        
        .alert-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #EF4444;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .ai-pulse {
            animation: aiPulse 2s ease-in-out infinite;
        }
        
        @keyframes aiPulse {
            0%, 100% { box-shadow: 0 0 5px rgba(139, 92, 246, 0.5); }
            50% { box-shadow: 0 0 20px rgba(139, 92, 246, 0.8); }
        }
        
        .insight-card {
            transition: all 0.3s ease;
        }
        
        .insight-card:hover {
            transform: scale(1.02);
        }
        
        .pdf-loading {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            z-index: 1000;
        }
    </style>
</head>

<body class="bg-gradient-to-br from-gray-50 to-blue-50 min-h-screen">
    <!-- Loading overlay pour PDF -->
    <div id="pdfLoading" class="pdf-loading">
        <div class="text-center">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-purple-600 mx-auto mb-4"></div>
            <p class="text-gray-700 font-semibold">Génération du rapport PDF...</p>
        </div>
    </div>

    <div class="container mx-auto p-6 max-w-7xl">
        
        <!-- NOUVELLE TOP BAR INTÉGRÉE -->
        <div class="bg-white shadow-sm rounded-2xl px-6 py-4 mb-8 flex items-center justify-between">
            <!-- Left -->
            <div class="flex items-center gap-6">
                <a href="dashboard.php"
                   class="flex items-center gap-2 bg-blue-800 hover:bg-blue-900 text-white px-5 py-2 rounded-full font-semibold transition">
                    ← Dashboard
                </a>
                <div class="flex items-center gap-2">
                    <span class="text-xl">⚠️</span>
                    <h1 class="text-3xl font-bold text-gray-800">Smart Analytics</h1>
                </div>
            </div>

            <!-- Right -->
            <div class="flex items-center gap-5">
                <!-- PDF -->
                <button onclick="generatePDFReport()"
                        class="bg-purple-600 hover:bg-purple-700 text-white px-5 py-2 rounded-lg shadow font-semibold transition btn-pdf">
                    📄 Générer Rapport PDF
                </button>

                <!-- Live Status -->
                <div class="flex items-center gap-2 text-sm font-semibold text-green-600">
                    <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                    <span id="liveStatusText">Real-time</span>
                </div>

                <!-- User -->
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

        <!-- Grille principale -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            
            <!-- Carte Statut Appareil -->
            <div class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-shadow duration-300">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-bold text-lg text-gray-800">Statut Appareil</h2>
                    <span id="deviceStatusIcon" class="text-2xl">📡</span>
                </div>
                
                <div class="space-y-3">
                    <div class="flex items-center space-x-2">
                        <span class="text-gray-600">État :</span>
                        <span id="deviceStatus" class="font-semibold text-gray-400">Vérification...</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="text-gray-600">Dernière activité :</span>
                        <span id="lastSeen" class="font-mono text-sm">--:--:--</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="text-gray-600">Uptime :</span>
                        <span id="uptime" class="font-mono text-sm">--</span>
                    </div>
                </div>
            </div>

            <!-- Carte Température -->
            <div class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-shadow duration-300">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-bold text-lg text-gray-800">Analyse Température</h2>
                    <span id="tempIcon" class="text-2xl">🌡️</span>
                </div>
                
                <div class="mb-4">
                    <p class="text-gray-600">Tendance : <span id="tempTrend" class="font-semibold">Analyse en cours...</span></p>
                    <p class="text-gray-600 mt-1">Valeur actuelle : <span id="currentTemp" class="font-bold text-xl">--°C</span></p>
                </div>
                
                <div class="chart-container">
                    <canvas id="tempTrendChart"></canvas>
                </div>
            </div>

            <!-- Carte Réseau -->
            <div class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-shadow duration-300">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-bold text-lg text-gray-800">Analyse Réseau</h2>
                    <span id="signalIcon" class="text-2xl">📶</span>
                </div>
                
                <div class="mb-4">
                    <p class="text-gray-600">Signal : <span id="signalTrend" class="font-semibold">Analyse en cours...</span></p>
                    <p class="text-gray-600 mt-1">Force : <span id="currentSignal" class="font-bold text-xl">--%</span></p>
                </div>
                
                <div class="chart-container">
                    <canvas id="signalTrendChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Section Smart Intelligence -->
        <div class="bg-gradient-to-r from-purple-50 to-blue-50 rounded-xl shadow-lg p-6 mb-6 ai-pulse">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-gradient-to-r from-purple-600 to-blue-600 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">Intelligence Artificielle</h2>
                        <p class="text-sm text-gray-600">Analyse prédictive et recommandations</p>
                    </div>
                </div>
                <span id="aiStatus" class="text-sm font-semibold text-green-600">● Actif</span>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Carte Insights -->
                <div class="bg-white rounded-lg p-4 shadow insight-card">
                    <h3 class="font-bold text-purple-700 mb-3 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                        </svg>
                        Insights en temps réel
                    </h3>
                    <div id="insightsContainer" class="space-y-2 text-sm">
                        <p class="text-gray-500">Analyse en cours...</p>
                    </div>
                </div>
                
                <!-- Carte Prédictions -->
                <div class="bg-white rounded-lg p-4 shadow insight-card">
                    <h3 class="font-bold text-blue-700 mb-3 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Prédictions
                    </h3>
                    <div id="predictionsContainer" class="space-y-2 text-sm">
                        <p class="text-gray-500">Calcul en cours...</p>
                    </div>
                </div>
                
                <!-- Carte Recommandations -->
                <div class="bg-white rounded-lg p-4 shadow insight-card">
                    <h3 class="font-bold text-green-700 mb-3 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Recommandations
                    </h3>
                    <div id="recommendationsContainer" class="space-y-2 text-sm">
                        <p class="text-gray-500">Génération en cours...</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Graphiques détaillés -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Courbe d'historique -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-lg">📊 Courbe d'Historique</h3>
                    <div class="flex space-x-2">
                        <button onclick="switchHistoryView('1h')" class="px-3 py-1 text-sm rounded bg-gray-100 hover:bg-gray-200">1h</button>
                        <button onclick="switchHistoryView('24h')" class="px-3 py-1 text-sm rounded bg-blue-100 hover:bg-blue-200">24h</button>
                        <button onclick="switchHistoryView('7j')" class="px-3 py-1 text-sm rounded bg-gray-100 hover:bg-gray-200">7j</button>
                    </div>
                </div>
                <div class="chart-container" style="height: 300px;">
                    <canvas id="historyChart"></canvas>
                </div>
                <div class="mt-4 flex justify-between text-sm text-gray-600">
                    <span>Min: <span id="historyMin">--</span></span>
                    <span>Max: <span id="historyMax">--</span></span>
                    <span>Moy: <span id="historyAvg">--</span></span>
                </div>
            </div>
            
            <!-- Courbe d'alerte -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-lg">⚠️ Courbe d'Alerte</h3>
                    <select id="alertTypeFilter" onchange="filterAlerts()" class="px-3 py-1 text-sm rounded border border-gray-300">
                        <option value="all">Toutes les alertes</option>
                        <option value="temperature">Température</option>
                        <option value="signal">Signal</option>
                        <option value="connection">Connexion</option>
                    </select>
                </div>
                <div class="chart-container" style="height: 300px;">
                    <canvas id="alertChart"></canvas>
                </div>
                <div class="mt-4">
                    <p class="text-sm text-gray-600">
                        Alertes aujourd'hui: <span id="todayAlerts" class="font-bold text-red-600">0</span>
                    </p>
                </div>
            </div>
        </div>
    </div>

   <script>
const CONFIG = {
    UPDATE_INTERVAL: 5000,
    OFFLINE_THRESHOLD: 30000,
    TEMP_THRESHOLDS: { CRITICAL: 28, WARNING: 24 },
    SIGNAL_THRESHOLDS: { CRITICAL: 30, WEAK: 50 }
};

const state = {
    lastESP32Update: Date.now(),
    alerts: [],
    charts: {}
};

function safeNumber(v, def = 0) {
    const n = parseFloat(v);
    return isNaN(n) ? def : n;
}

function initCharts() {

    state.charts.temp = new Chart(document.getElementById('tempTrendChart'), {
        type: 'line',
        data: { labels: [], datasets: [{ data: [], borderColor: '#f59e0b', backgroundColor: '#fbbf2422', fill: true, tension: .4 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins:{legend:{display:false}} }
    });

    state.charts.signal = new Chart(document.getElementById('signalTrendChart'), {
        type: 'line',
        data: { labels: [], datasets: [{ data: [], borderColor: '#3b82f6', backgroundColor: '#3b82f622', fill: true, tension: .4 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins:{legend:{display:false}} }
    });

    state.charts.history = new Chart(document.getElementById('historyChart'), {
        type: 'line',
        data: {
            labels: [],
            datasets: [
                { label: 'Température', data: [], borderColor: '#f59e0b', yAxisID:'y' },
                { label: 'Signal', data: [], borderColor: '#3b82f6', yAxisID:'y1' }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y:{ type:'linear', position:'left' },
                y1:{ type:'linear', position:'right', grid:{ drawOnChartArea:false } }
            }
        }
    });

    state.charts.alert = new Chart(document.getElementById('alertChart'), {
        type: 'bar',
        data: {
            labels: ['Initialisation'],
            datasets: [{
                label: 'Alertes',
                data: [0],
                backgroundColor: ['#D1D5DB']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
}

function pushChart(chart, value, max = 20) {
    const now = new Date().toLocaleTimeString();
    chart.data.labels.push(now);
    chart.data.datasets[0].data.push(value);

    while (chart.data.labels.length > max) {
        chart.data.labels.shift();
        chart.data.datasets[0].data.shift();
    }

    chart.update();
}

function updateHistory(temp, signal) {
    const c = state.charts.history;
    const now = new Date().toLocaleTimeString();

    c.data.labels.push(now);
    c.data.datasets[0].data.push(temp);
    c.data.datasets[1].data.push(signal);

    while (c.data.labels.length > 40) {
        c.data.labels.shift();
        c.data.datasets[0].data.shift();
        c.data.datasets[1].data.shift();
    }

    c.update();

    const temps = c.data.datasets[0].data;
    if (temps.length) {
        const avg = temps.reduce((a,b)=>a+b,0)/temps.length;
        document.getElementById('historyMin').textContent = Math.min(...temps).toFixed(1)+'°C';
        document.getElementById('historyMax').textContent = Math.max(...temps).toFixed(1)+'°C';
        document.getElementById('historyAvg').textContent = avg.toFixed(1)+'°C';
    }
}

function addAlert(type, message) {

    const now = new Date();
    const hour = now.getHours().toString().padStart(2,'0') + ':00';

    state.alerts.push({
        hour,
        type,
        message,
        date: now.toDateString()
    });

    updateAlertChart();
}

async function updateAlertChart() {
    try {
        const response = await fetch("get_alerts.php?user_id=<?= $user_id ?>");
        const result = await response.json();

        if (!result.success || !result.alerts || result.alerts.length === 0) {
            state.charts.alert.data.labels = ['Aucune'];
            state.charts.alert.data.datasets[0].data = [0];
            state.charts.alert.data.datasets[0].backgroundColor = ['#D1D5DB'];
            state.charts.alert.update();
            document.getElementById('todayAlerts').textContent = 0;
            return;
        }

        const filter = document.getElementById('alertTypeFilter').value;
        let alerts = result.alerts;

        if (filter !== 'all') {
            alerts = alerts.filter(a => {
                return (a.message || '').toLowerCase().includes(filter);
            });
        }

        const grouped = {};

        alerts.forEach(alert => {
            const d = new Date(alert.created_at || alert.timestamp || Date.now());
            const h = d.getHours().toString().padStart(2,'0') + ":00";
            grouped[h] = (grouped[h] || 0) + 1;
        });

        const labels = Object.keys(grouped);

        state.charts.alert.data.labels = labels;
        state.charts.alert.data.datasets[0].data = labels.map(h => grouped[h]);
        state.charts.alert.data.datasets[0].backgroundColor = labels.map(() => '#EF4444');
        state.charts.alert.update();

        const today = new Date().toDateString();

        const todayCount = alerts.filter(a => {
            const d = new Date(a.created_at || a.timestamp);
            return d.toDateString() === today;
        }).length;

        document.getElementById('todayAlerts').textContent = todayCount;

    } catch (err) {
        console.error("Alert chart error:", err);
    }
}

function filterAlerts() {
    updateAlertChart();
}

function analyzeTemp(temp) {

    document.getElementById('currentTemp').textContent = temp.toFixed(1)+'°C';

    let txt='Stable', cls='status-good', icon='🌡️';

    if (temp > 28) {
        txt='Critique'; cls='status-critical'; icon='🔥';
        addAlert('temperature','Température critique');
    }
    else if (temp > 24) {
        txt='Élevée'; cls='status-warning';
    }

    document.getElementById('tempTrend').textContent = txt;
    document.getElementById('tempTrend').className = 'font-semibold '+cls;
    document.getElementById('tempIcon').textContent = icon;

    pushChart(state.charts.temp, temp);
}

function analyzeSignal(signal) {

    document.getElementById('currentSignal').textContent = signal+'%';

    let txt='Bon', cls='status-good';

    if (signal < 30) {
        txt='Critique'; cls='status-critical';
        addAlert('signal','Signal faible');
    }
    else if (signal < 50) {
        txt='Faible'; cls='status-warning';
    }

    document.getElementById('signalTrend').textContent = txt;
    document.getElementById('signalTrend').className = 'font-semibold '+cls;

    pushChart(state.charts.signal, signal);
}

function updateAI(temp, signal) {

    let prediction = temp > 27 ? 'Risque surchauffe' : 'Prévision OK';
    let recommendation = signal < 40 ? 'Vérifier réseau' : 'Système stable';

    document.getElementById('insightsContainer').innerHTML = `
        <p>Température: ${temp.toFixed(1)}°C</p>
        <p>Signal: ${signal}%</p>
    `;

    document.getElementById('predictionsContainer').innerHTML = `
        <p>${prediction}</p>
    `;

    document.getElementById('recommendationsContainer').innerHTML = `
        <p>${recommendation}</p>
    `;
}

function checkOffline() {

    const diff = Date.now() - state.lastESP32Update;
    const online = diff < CONFIG.OFFLINE_THRESHOLD;

    document.getElementById('deviceStatus').textContent = online ? 'En ligne' : 'Hors ligne';
    document.getElementById('deviceStatus').className = 'font-semibold ' + (online ? 'status-online':'status-offline');
    document.getElementById('liveStatusText').textContent = online ? 'Real-time' : 'Offline';
}

function updateUptime() {
    const sec = Math.floor((Date.now() - state.lastESP32Update)/1000);
    document.getElementById('uptime').textContent = sec+' s';
}

async function loadAnalytics() {
    try {
        const r = await fetch("get_latest_data.php?user_id=<?= $user_id ?>");
        const json = await r.json();

        if (!json.success || !json.data) return;

        state.lastESP32Update = Date.now();

        document.getElementById('lastSeen').textContent = new Date().toLocaleTimeString();

        const temp = safeNumber(json.data.temperature);
        const signal = safeNumber(json.data.signal_strength);

        analyzeTemp(temp);
        analyzeSignal(signal);
        updateHistory(temp, signal);
        updateAI(temp, signal);

        await updateAlertChart();

    } catch(e) {
        console.log(e);
    }
}

async function generatePDFReport() {

    document.getElementById('pdfLoading').style.display='block';

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();

    doc.text("Smart Analytics",20,20);
    doc.text("Utilisateur: <?= htmlspecialchars($username) ?>",20,30);
    doc.text("Date: "+new Date().toLocaleString(),20,40);

    doc.text("Température: "+document.getElementById('currentTemp').textContent,20,60);
    doc.text("Signal: "+document.getElementById('currentSignal').textContent,20,70);

    const img = document.getElementById('historyChart').toDataURL();
    doc.addImage(img,'PNG',15,90,180,80);

    doc.save("rapport.pdf");

    document.getElementById('pdfLoading').style.display='none';
}

function init() {
    initCharts();
    loadAnalytics();

    setInterval(loadAnalytics, 5000);
    setInterval(checkOffline, 5000);
    setInterval(updateUptime, 1000);
}

document.addEventListener('DOMContentLoaded', init);
</script>
</body>
</html>
