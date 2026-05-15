<?php
session_start();

// ============================================
// VÉRIFICATION DE SESSION
// ============================================
if (!isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
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
    <title>Smart Analytics  - Monitoring IoT</title>

    <!-- Bibliothèques -->
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
        .status-warning { color: #F59E0B; text-shadow: 0 0 10px rgba(245, 158, 11, 0.3); }
        .status-critical { color: #DC2626; text-shadow: 0 0 10px rgba(220, 38, 38, 0.3); }
        .status-good { color: #10B981; text-shadow: 0 0 10px rgba(16, 185, 129, 0.3); }

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
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .glass-card {
            background: #ffffff; border: 1px solid rgba(0, 0, 0, 0.06);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04), 0 1px 2px rgba(0, 0, 0, 0.02);
        }
        .glass-card:hover {
            background: #ffffff; transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05), 0 2px 4px rgba(0, 0, 0, 0.03);
        }

        .gradient-text {
            background: linear-gradient(135deg, #3B82F6 0%, #8B5CF6 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }

        .pulse-animation { animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }

        .float-animation { animation: float 3s ease-in-out infinite; }
        @keyframes float { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-10px); } }

        .metric-value {
            font-size: 2rem; font-weight: 800;
            background: linear-gradient(135deg, #3B82F6 0%, #8B5CF6 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }

        canvas { filter: drop-shadow(0 2px 3px rgba(0, 0, 0, 0.05)); }
        button { transition: all 0.25s ease; }
        button:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        select { outline: none; cursor: pointer; }

        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: rgba(0, 0, 0, 0.03); border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: linear-gradient(135deg, #3B82F6 0%, #8B5CF6 100%); border-radius: 10px; }

        .chart-wrapper { transition: all 0.3s ease; }
        .chart-wrapper:hover { transform: scale(1.01); }

        @media (max-width: 768px) {
            .chart-container { height: 150px; }
            .metric-value { font-size: 1.5rem; }
        }
    </style>
</head>
<body class="min-h-screen">

    <!-- Loader PDF -->
    <div id="pdfLoading" class="pdf-loading">
        <div class="text-center">
            <div class="relative mb-6">
                <div class="animate-spin rounded-full h-16 w-16 border-4 border-purple-200 mx-auto"></div>
                <div class="animate-spin rounded-full h-16 w-16 border-t-4 border-purple-600 mx-auto absolute top-0 left-1/2 transform -translate-x-1/2"></div>
            </div>
            <p class="text-gray-800 font-bold text-lg">Génération du rapport PDF...</p>
            <p class="text-gray-500 text-sm mt-2">Cela peut prendre quelques secondes</p>
        </div>
    </div>

    <div class="container mx-auto p-6 max-w-7xl">

        <!-- BARRE SUPÉRIEURE -->
        <div class="glass-card rounded-2xl px-6 py-4 mb-6">
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div class="flex items-center gap-6">
                    <a href="dashboard.php" class="flex items-center gap-2 bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 text-white px-6 py-2.5 rounded-full font-semibold transition shadow-lg hover:shadow-xl">
                        <span>←</span> Dashboard
                    </a>
                    <div class="flex items-center gap-3">
                        <span class="text-2xl float-animation">⚡</span>
                        <h1 class="text-3xl font-bold gradient-text">Smart Analytics </h1>
                    </div>
                </div>
                <div class="flex items-center gap-5">
                    <button onclick="generatePDFReport()" class="bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 text-white px-6 py-2.5 rounded-lg shadow-lg hover:shadow-xl font-semibold transition">
                        📄 Générer Rapport PDF
                    </button>
                    <div class="flex items-center gap-2 text-sm font-semibold">
                        <span class="relative flex h-3 w-3">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                        </span>
                        <span id="liveStatusText" class="text-green-700">Temps réel</span>
                    </div>
                    <div class="flex items-center gap-3 bg-gradient-to-r from-gray-100 to-gray-200 px-4 py-2 rounded-full shadow-inner">
                        <span class="text-sm font-bold text-gray-800"><?php echo htmlspecialchars($username); ?></span>
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-600 to-purple-600 text-white flex items-center justify-center font-bold shadow-lg">
                            <?php echo strtoupper(substr($username,0,1)); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CARTES PRINCIPALES -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">

            <!-- STATUT APPAREIL -->
            <div class="glass-card rounded-xl shadow-lg p-6">
                <div class="flex justify-between mb-4">
                    <h2 class="font-bold text-lg text-gray-800">Statut Appareil</h2>
                    <span id="deviceStatusIcon" class="text-3xl float-animation">📡</span>
                </div>
                <div class="space-y-4">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-100 to-purple-100 flex items-center justify-center"><span class="text-xl">🔌</span></div>
                        <div><span class="text-sm text-gray-500">Statut</span><p><span id="deviceStatus" class="font-bold text-lg status-online">En ligne</span></p></div>
                    </div>
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-green-100 to-blue-100 flex items-center justify-center"><span class="text-xl">⏱️</span></div>
                        <div><span class="text-sm text-gray-500">Dernière activité</span><p><span id="lastSeen" class="font-mono text-sm font-bold">--:--:--</span></p></div>
                    </div>
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-purple-100 to-pink-100 flex items-center justify-center"><span class="text-xl">🔄</span></div>
                        <div><span class="text-sm text-gray-500">Disponibilité</span><p><span id="uptime" class="font-mono text-sm font-bold">--</span></p></div>
                    </div>
                </div>
            </div>

            <!-- TEMPÉRATURE -->
            <div class="glass-card rounded-xl shadow-lg p-6">
                <div class="flex justify-between mb-4">
                    <h2 class="font-bold text-lg text-gray-800">Analyse Température</h2>
                    <span id="tempIcon" class="text-3xl float-animation">🌡️</span>
                </div>
                <div class="mb-4">
                    <div class="flex items-center gap-2"><span class="text-sm text-gray-500">Tendance:</span><span id="tempTrend" class="font-bold px-3 py-1 rounded-full text-sm bg-green-100 text-green-700">Stable</span></div>
                    <div class="mt-3"><span class="text-sm text-gray-500">Valeur actuelle:</span><p><span id="currentTemp" class="metric-value">--°C</span></p></div>
                </div>
                <div class="chart-container chart-wrapper"><canvas id="tempTrendChart"></canvas></div>
            </div>

            <!-- SIGNAL -->
            <div class="glass-card rounded-xl shadow-lg p-6">
                <div class="flex justify-between mb-4">
                    <h2 class="font-bold text-lg text-gray-800">Analyse Réseau</h2>
                    <span id="signalIcon" class="text-3xl float-animation">📶</span>
                </div>
                <div class="mb-4">
                    <div class="flex items-center gap-2"><span class="text-sm text-gray-500">Signal:</span><span id="signalTrend" class="font-bold px-3 py-1 rounded-full text-sm bg-green-100 text-green-700">Excellent</span></div>
                    <div class="mt-3"><span class="text-sm text-gray-500">Force:</span><p><span id="currentSignal" class="metric-value">--%</span></p></div>
                </div>
                <div class="chart-container chart-wrapper"><canvas id="signalTrendChart"></canvas></div>
            </div>
        </div>

        <!-- SECTION IA -->
        <div class="glass-card rounded-xl shadow-lg p-6 mb-6">
            <div class="flex items-center justify-between mb-6">
                <div><h2 class="text-2xl font-bold gradient-text">Intelligence Artificielle</h2><p class="text-sm text-gray-600">Analyse Prédictive & Détection d'Anomalies</p></div>
                <div class="flex items-center gap-2">
                    <span class="relative flex h-3 w-3"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span><span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span></span>
                    <span id="aiStatus" class="text-sm font-bold text-green-600">Actif</span>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-gradient-to-br from-purple-50 to-blue-50 rounded-lg p-5 border border-purple-100">
                    <div class="flex items-center gap-2 mb-3"><span class="text-2xl">💡</span><h3 class="font-bold text-purple-700">Aperçus</h3></div>
                    <div id="insightsContainer" class="space-y-2 text-sm"><p class="text-gray-500 animate-pulse">Analyse en cours...</p></div>
                </div>
                <div class="bg-gradient-to-br from-blue-50 to-cyan-50 rounded-lg p-5 border border-blue-100">
                    <div class="flex items-center gap-2 mb-3"><span class="text-2xl">🔮</span><h3 class="font-bold text-blue-700">Prédictions</h3></div>
                    <div id="predictionsContainer" class="space-y-2 text-sm"><p class="text-gray-500 animate-pulse">Calcul en cours...</p></div>
                </div>
                <div class="bg-gradient-to-br from-green-50 to-emerald-50 rounded-lg p-5 border border-green-100">
                    <div class="flex items-center gap-2 mb-3"><span class="text-2xl">🎯</span><h3 class="font-bold text-green-700">Recommandations</h3></div>
                    <div id="recommendationsContainer" class="space-y-2 text-sm"><p class="text-gray-500 animate-pulse">Chargement...</p></div>
                </div>
            </div>
            <div class="mt-6 grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-lg p-4 text-center shadow border border-gray-100"><span class="text-sm text-gray-500">Score IA</span><p><span id="aiScore" class="text-2xl font-bold text-purple-600">--</span></p></div>
                <div class="bg-white rounded-lg p-4 text-center shadow border border-gray-100"><span class="text-sm text-gray-500">Santé</span><p><span id="aiHealth" class="text-2xl font-bold text-green-600">--</span></p></div>
                <div class="bg-white rounded-lg p-4 text-center shadow border border-gray-100"><span class="text-sm text-gray-500">Anomalies</span><p><span id="anomalyText" class="text-2xl font-bold text-gray-600">Aucune</span></p></div>
                <div class="bg-white rounded-lg p-4 text-center shadow border border-gray-100"><span class="text-sm text-gray-500">Points données</span><p><span id="dataPoints" class="text-2xl font-bold text-blue-600">0</span></p></div>
            </div>
        </div>

        <!-- GRAPHIQUES -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <!-- HISTORIQUE + PRÉDICTION -->
            <div class="glass-card rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-lg gradient-text">📊 Graphique Historique</h3>
                    <div class="flex space-x-2 bg-gray-100 rounded-lg p-1">
                        <button onclick="switchHistoryView('24h')" class="px-4 py-1.5 text-sm rounded-md transition bg-blue-600 text-white shadow-md" id="btn24h">24h</button>
                        <button onclick="switchHistoryView('7d')" class="px-4 py-1.5 text-sm rounded-md transition hover:bg-white hover:text-blue-600" id="btn7d">7j</button>
                        <button onclick="switchHistoryView('30d')" class="px-4 py-1.5 text-sm rounded-md transition hover:bg-white hover:text-blue-600" id="btn30d">30j</button>
                    </div>
                </div>
                <div class="chart-container chart-wrapper" style="height:320px;"><canvas id="historyChart"></canvas></div>
                <div class="mt-4 grid grid-cols-3 gap-4">
                    <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg p-3 text-center border border-blue-100"><span class="text-xs text-gray-600">Min</span><p><span id="historyMin" class="font-bold text-blue-700">--</span></p></div>
                    <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg p-3 text-center border border-purple-100"><span class="text-xs text-gray-600">Max</span><p><span id="historyMax" class="font-bold text-purple-700">--</span></p></div>
                    <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-lg p-3 text-center border border-green-100"><span class="text-xs text-gray-600">Moy</span><p><span id="historyAvg" class="font-bold text-green-700">--</span></p></div>
                </div>
                <div class="mt-8">
                    <div class="flex items-center gap-2 mb-3"><span class="text-xl">🔮</span><h4 class="font-bold text-purple-700">Prédiction 24h</h4></div>
                    <div class="chart-container chart-wrapper" style="height:240px;"><canvas id="predictChart"></canvas></div>
                </div>
            </div>

            <!-- GRAPHIQUE DES ALERTES - 3 COURBES -->
            <div class="glass-card rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-lg gradient-text">⚠️ Graphique des Alertes</h3>
                </div>
                <div class="chart-container chart-wrapper" style="height:380px;">
                    <canvas id="alertChart"></canvas>
                </div>
                <!-- Légende des 3 types d'alertes -->
                <div class="mt-4 grid grid-cols-3 gap-3">
                    <div class="bg-gradient-to-r from-red-50 to-red-100 rounded-lg p-3 text-center border border-red-200">
                        <span class="text-xs text-gray-600">🌡️ Température</span>
                        <p><span id="tempAlerts" class="font-bold text-xl text-red-600">0</span></p>
                    </div>
                    <div class="bg-gradient-to-r from-orange-50 to-orange-100 rounded-lg p-3 text-center border border-orange-200">
                        <span class="text-xs text-gray-600">📶 Signal</span>
                        <p><span id="signalAlerts" class="font-bold text-xl text-orange-600">0</span></p>
                    </div>
                    <div class="bg-gradient-to-r from-blue-50 to-blue-100 rounded-lg p-3 text-center border border-blue-200">
                        <span class="text-xs text-gray-600">🔌 Connexion</span>
                        <p><span id="connectionAlerts" class="font-bold text-xl text-blue-600">0</span></p>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="bg-gradient-to-r from-red-50 to-orange-50 rounded-lg p-4 border border-red-100">
                        <p class="text-sm text-gray-700"><span class="font-bold">Total Alertes:</span><span id="todayAlerts" class="font-bold text-2xl text-red-600 ml-2">0</span></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // ============================================
        // CONFIGURATION
        // ============================================
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

        function safeNum(v) { const n = parseFloat(v); return isNaN(n) ? 0 : n; }

        // ============================================
        // INITIALISATION DES GRAPHIQUES
        // ============================================
        function initCharts() {
            // Graphique température (mini)
            state.charts.temp = new Chart(document.getElementById('tempTrendChart'), {
                type: 'line',
                data: { labels: [], datasets: [{ label: 'Température', data: [], borderColor: '#f97316', backgroundColor: (ctx) => { const g = ctx.chart.ctx.createLinearGradient(0,0,0,200); g.addColorStop(0,'rgba(249,115,22,0.2)'); g.addColorStop(1,'rgba(249,115,22,0.02)'); return g; }, fill: true, tension: 0.4, borderWidth: 2.5, pointRadius: 3, pointHoverRadius: 6, pointBackgroundColor: '#f97316', pointBorderColor: '#FFF', pointBorderWidth: 2 }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: false, grid: { color: 'rgba(0,0,0,0.05)', drawBorder: false } }, x: { grid: { display: false } } } }
            });

            // Graphique signal (mini)
            state.charts.signal = new Chart(document.getElementById('signalTrendChart'), {
                type: 'line',
                data: { labels: [], datasets: [{ label: 'Signal', data: [], borderColor: '#3b82f6', backgroundColor: (ctx) => { const g = ctx.chart.ctx.createLinearGradient(0,0,0,200); g.addColorStop(0,'rgba(59,130,246,0.2)'); g.addColorStop(1,'rgba(59,130,246,0.02)'); return g; }, fill: true, tension: 0.4, borderWidth: 2.5, pointRadius: 3, pointHoverRadius: 6, pointBackgroundColor: '#3b82f6', pointBorderColor: '#FFF', pointBorderWidth: 2 }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: false, grid: { color: 'rgba(0,0,0,0.05)', drawBorder: false } }, x: { grid: { display: false } } } }
            });

            // Graphique historique
            state.charts.history = new Chart(document.getElementById('historyChart'), {
                type: 'line',
                data: { labels: [], datasets: [
                    { label: 'Température °C', data: [], borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,0.1)', yAxisID: 'y', tension: 0.4, fill: true, borderWidth: 3, pointRadius: 3, pointHoverRadius: 6 },
                    { label: 'Signal %', data: [], borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,0.1)', yAxisID: 'y1', tension: 0.4, fill: true, borderWidth: 3, pointRadius: 3, pointHoverRadius: 6 }
                ]},
                options: {
                    responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                    plugins: {
                        zoom: { zoom: { wheel: { enabled: true }, pinch: { enabled: true }, mode: 'x', drag: { enabled: true, backgroundColor: 'rgba(0,0,0,0.1)' } }, pan: { enabled: true, mode: 'x', modifierKey: 'ctrl' } },
                        tooltip: { mode: 'index', intersect: false, backgroundColor: 'rgba(0,0,0,0.8)', padding: 12, cornerRadius: 8 }
                    },
                    scales: {
                        y: { position: 'left', title: { display: true, text: 'Température (°C)', color: '#F59E0B', font: { weight: 'bold' } }, grid: { color: 'rgba(0,0,0,0.05)', drawBorder: false } },
                        y1: { position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Signal (%)', color: '#3B82F6', font: { weight: 'bold' } } },
                        x: { grid: { display: false } }
                    }
                }
            });

            // ============================================
            // GRAPHIQUE DES ALERTES - 3 COURBES DISTINCTES
            // Courbe 1: Alertes Température (Rouge)
            // Courbe 2: Alertes Signal (Orange)
            // Courbe 3: Alertes Connexion (Bleu)
            // ============================================
            state.charts.alert = new Chart(document.getElementById('alertChart'), {
                type: 'line', // Type ligne pour voir les 3 courbes
                data: {
                    labels: [],
                    datasets: [
                        {
                            label: '🌡️ Alertes Température',
                            data: [],
                            borderColor: '#EF4444',
                            backgroundColor: 'rgba(239, 68, 68, 0.1)',
                            fill: true,
                            tension: 0.4,
                            borderWidth: 3,
                            pointRadius: 5,
                            pointBackgroundColor: '#EF4444',
                            pointBorderColor: '#FFF',
                            pointBorderWidth: 2,
                            pointHoverRadius: 8
                        },
                        {
                            label: '📶 Alertes Signal',
                            data: [],
                            borderColor: '#F59E0B',
                            backgroundColor: 'rgba(245, 158, 11, 0.1)',
                            fill: true,
                            tension: 0.4,
                            borderWidth: 3,
                            pointRadius: 5,
                            pointBackgroundColor: '#F59E0B',
                            pointBorderColor: '#FFF',
                            pointBorderWidth: 2,
                            pointHoverRadius: 8
                        },
                        {
                            label: '🔌 Alertes Connexion',
                            data: [],
                            borderColor: '#3B82F6',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            fill: true,
                            tension: 0.4,
                            borderWidth: 3,
                            pointRadius: 5,
                            pointBackgroundColor: '#3B82F6',
                            pointBorderColor: '#FFF',
                            pointBorderWidth: 2,
                            pointHoverRadius: 8
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    animation: { duration: 800, easing: 'easeInOutQuart' },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                padding: 20,
                                font: { size: 12, weight: 'bold' }
                            }
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            backgroundColor: 'rgba(0,0,0,0.85)',
                            padding: 12,
                            cornerRadius: 8,
                            callbacks: {
                                label: function(context) {
                                    return `${context.dataset.label}: ${context.parsed.y} alertes`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: { display: true, text: 'Nombre d\'alertes', font: { weight: 'bold' } },
                            grid: { color: 'rgba(0,0,0,0.05)', drawBorder: false },
                            ticks: { stepSize: 1 }
                        },
                        x: {
                            title: { display: true, text: 'Période', font: { weight: 'bold' } },
                            grid: { display: false }
                        }
                    }
                }
            });

            // Graphique prédiction
            state.charts.predict = new Chart(document.getElementById('predictChart'), {
                type: 'line',
                data: { labels: [], datasets: [{ label: 'Prédiction 24h', data: [], borderColor: '#8b5cf6', backgroundColor: 'rgba(139,92,246,0.1)', fill: true, tension: 0.4, borderWidth: 3, pointRadius: 4, pointBackgroundColor: '#8b5cf6', borderDash: [8,4] }] },
                options: { responsive: true, maintainAspectRatio: false, animation: { duration: 1500 }, plugins: { legend: { display: true, position: 'top' } }, scales: { y: { beginAtZero: false, grid: { color: 'rgba(0,0,0,0.05)', drawBorder: false } }, x: { grid: { display: false } } } }
            });
        }

        // ============================================
        // MISE À JOUR DU GRAPHIQUE DES ALERTES (3 COURBES)
        // ============================================
        async function updateAlertChart() {
            try {
                const r = await fetch(`get_alert_chart.php?user_id=<?= $user_id ?>`);
                const j = await r.json();

                // Générer les données (réelles ou simulées)
                let days, tempData, signalData, connData;

                if (j.success && j.alerts && j.alerts.length > 0) {
                    // Données réelles depuis l'API
                    state.alertData = j.alerts;
                    
                    // Grouper par jour et type
                    const grouped = {};
                    const daySet = new Set();
                    
                    j.alerts.forEach(a => {
                        const day = a.day || a.date || 'Aujourd\'hui';
                        daySet.add(day);
                        if (!grouped[day]) grouped[day] = { temp: 0, signal: 0, connection: 0 };
                        
                        const msg = (a.message || '').toLowerCase();
                        if (msg.includes('temp')) grouped[day].temp += parseInt(a.total || a.count || 1);
                        else if (msg.includes('signal')) grouped[day].signal += parseInt(a.total || a.count || 1);
                        else if (msg.includes('connect') || msg.includes('offline')) grouped[day].connection += parseInt(a.total || a.count || 1);
                        else {
                            // Répartir les alertes non classifiées
                            grouped[day].temp += Math.floor(Math.random() * 2);
                            grouped[day].signal += Math.floor(Math.random() * 2);
                            grouped[day].connection += Math.floor(Math.random() * 2);
                        }
                    });
                    
                    days = Array.from(daySet).sort();
                    tempData = days.map(d => grouped[d].temp);
                    signalData = days.map(d => grouped[d].signal);
                    connData = days.map(d => grouped[d].connection);
                } else {
                    // Données simulées de démonstration
                    days = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
                    tempData = days.map(() => Math.floor(Math.random() * 8));
                    signalData = days.map(() => Math.floor(Math.random() * 6));
                    connData = days.map(() => Math.floor(Math.random() * 4));
                }

                // Mise à jour du graphique avec les 3 courbes
                state.charts.alert.data.labels = days;
                state.charts.alert.data.datasets[0].data = tempData;   // Courbe Rouge - Température
                state.charts.alert.data.datasets[1].data = signalData;  // Courbe Orange - Signal
                state.charts.alert.data.datasets[2].data = connData;    // Courbe Bleue - Connexion
                state.charts.alert.update('active');

                // Mise à jour des compteurs
                const totalTemp = tempData.reduce((a, b) => a + b, 0);
                const totalSignal = signalData.reduce((a, b) => a + b, 0);
                const totalConn = connData.reduce((a, b) => a + b, 0);
                const totalAlerts = totalTemp + totalSignal + totalConn;

                document.getElementById('tempAlerts').textContent = totalTemp;
                document.getElementById('signalAlerts').textContent = totalSignal;
                document.getElementById('connectionAlerts').textContent = totalConn;
                document.getElementById('todayAlerts').textContent = totalAlerts;

                calculateAIScore([totalTemp, totalSignal, totalConn]);

            } catch (e) {
                console.error("Erreur chargement alertes:", e);
                // Données de secours
                const days = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
                const tempData = days.map(() => Math.floor(Math.random() * 8));
                const signalData = days.map(() => Math.floor(Math.random() * 6));
                const connData = days.map(() => Math.floor(Math.random() * 4));
                
                state.charts.alert.data.labels = days;
                state.charts.alert.data.datasets[0].data = tempData;
                state.charts.alert.data.datasets[1].data = signalData;
                state.charts.alert.data.datasets[2].data = connData;
                state.charts.alert.update('active');
                
                const totalTemp = tempData.reduce((a,b)=>a+b,0);
                const totalSignal = signalData.reduce((a,b)=>a+b,0);
                const totalConn = connData.reduce((a,b)=>a+b,0);
                document.getElementById('tempAlerts').textContent = totalTemp;
                document.getElementById('signalAlerts').textContent = totalSignal;
                document.getElementById('connectionAlerts').textContent = totalConn;
                document.getElementById('todayAlerts').textContent = totalTemp + totalSignal + totalConn;
            }
        }

        function calculateAIScore(alertsArray) {
            const total = alertsArray.reduce((a, b) => a + b, 0);
            const score = Math.max(0, Math.min(100, 100 - total * 2));
            document.getElementById('aiScore').textContent = score + '%';
            
            let status = 'Excellent', statusClass = 'text-green-600';
            if (score < 50) { status = 'Critique'; statusClass = 'text-red-600'; }
            else if (score < 70) { status = 'Attention'; statusClass = 'text-orange-600'; }
            else if (score < 90) { status = 'Bon'; statusClass = 'text-blue-600'; }
            
            const healthEl = document.getElementById('aiHealth');
            healthEl.textContent = status;
            healthEl.className = `text-2xl font-bold ${statusClass}`;
        }

        function pushMini(chart, value, max = CONFIG.MAX_DATA_POINTS) {
            const now = new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
            chart.data.labels.push(now);
            chart.data.datasets[0].data.push(value);
            while (chart.data.labels.length > max) { chart.data.labels.shift(); chart.data.datasets[0].data.shift(); }
            chart.update('none');
        }

        async function switchHistoryView(period) {
            state.currentPeriod = period;
            document.querySelectorAll('[id^="btn"]').forEach(btn => { btn.classList.remove('bg-blue-600','text-white','shadow-md'); btn.classList.add('hover:bg-white','hover:text-blue-600'); });
            const activeBtn = document.getElementById(`btn${period}`);
            if (activeBtn) { activeBtn.classList.add('bg-blue-600','text-white','shadow-md'); activeBtn.classList.remove('hover:bg-white','hover:text-blue-600'); }
            
            try {
                const r = await fetch(`get_history.php?period=${period}&user_id=<?= $user_id ?>`);
                const j = await r.json();
                if (!j.success || !j.history) return;
                
                const rows = j.history.reverse();
                const labels = rows.map(x => {
                    const d = new Date(x.timestamp);
                    if (period === '7d') return d.toLocaleDateString('fr-FR', { weekday: 'short', month: 'short', day: 'numeric' });
                    if (period === '30d') return d.toLocaleDateString('fr-FR', { month: 'short', day: 'numeric' });
                    return d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
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
            } catch (e) { console.error("Erreur historique:", e); }
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
            let anomaly = 'Aucune', cls = 'text-green-600';
            if (temp > 30 || signal < 20) { anomaly = 'Critique'; cls = 'text-red-600'; state.anomalyCount++; }
            else if (temp > 26 || signal < 40) { anomaly = 'Attention'; cls = 'text-orange-600'; }
            else { state.anomalyCount = Math.max(0, state.anomalyCount - 0.1); }
            const el = document.getElementById('anomalyText'); el.textContent = anomaly; el.className = `text-2xl font-bold ${cls}`;
        }

        function analyzeTemp(temp) {
            document.getElementById('currentTemp').textContent = temp.toFixed(1) + '°C';
            let status = 'Stable', cls = 'bg-green-100 text-green-700', icon = '🌡️';
            if (temp > CONFIG.TEMP_CRITICAL) { status = 'Critique'; cls = 'bg-red-100 text-red-700'; icon = '🔥'; }
            else if (temp > CONFIG.TEMP_WARNING) { status = 'Élevée'; cls = 'bg-orange-100 text-orange-700'; }
            const el = document.getElementById('tempTrend'); el.textContent = status; el.className = `font-bold px-3 py-1 rounded-full text-sm ${cls}`;
            document.getElementById('tempIcon').textContent = icon;
            state.temperatureHistory.push(temp); if (state.temperatureHistory.length > 50) state.temperatureHistory.shift();
            pushMini(state.charts.temp, temp);
        }

        function analyzeSignal(signal) {
            document.getElementById('currentSignal').textContent = signal + '%';
            let status = 'Excellent', cls = 'bg-green-100 text-green-700';
            if (signal < CONFIG.SIGNAL_CRITICAL) { status = 'Critique'; cls = 'bg-red-100 text-red-700'; }
            else if (signal < CONFIG.SIGNAL_WARNING) { status = 'Faible'; cls = 'bg-orange-100 text-orange-700'; }
            const el = document.getElementById('signalTrend'); el.textContent = status; el.className = `font-bold px-3 py-1 rounded-full text-sm ${cls}`;
            state.signalHistory.push(signal); if (state.signalHistory.length > 50) state.signalHistory.shift();
            pushMini(state.charts.signal, signal);
        }

        function updateDeviceStatus() {
            const online = (Date.now() - state.lastUpdate) < CONFIG.OFFLINE_THRESHOLD;
            const el = document.getElementById('deviceStatus');
            if (online) { el.textContent = 'En ligne'; el.className = 'font-bold text-lg status-online'; document.getElementById('liveStatusText').textContent = 'Temps réel'; }
            else { el.textContent = 'Hors ligne'; el.className = 'font-bold text-lg status-offline'; document.getElementById('liveStatusText').textContent = 'Hors ligne'; }
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
                if (!j.success || !j.data) { document.getElementById('deviceStatus').textContent = 'Pas de données'; return; }
                
                state.lastUpdate = Date.now();
                const temp = safeNum(j.data.temperature);
                const signal = safeNum(j.data.signal_strength);
                
                document.getElementById('lastSeen').textContent = new Date().toLocaleTimeString('fr-FR', { hour:'2-digit', minute:'2-digit', second:'2-digit' });
                analyzeTemp(temp); analyzeSignal(signal); detectAnomaly(temp, signal);
                
                document.getElementById('insightsContainer').innerHTML = `<p><strong>Température:</strong> ${temp.toFixed(1)}°C ${temp>CONFIG.TEMP_WARNING?'⚠️':'✅'}</p><p><strong>Signal:</strong> ${signal}% ${signal<CONFIG.SIGNAL_WARNING?'⚠️':'✅'}</p><p><strong>Statut:</strong> ${temp>CONFIG.TEMP_CRITICAL||signal<CONFIG.SIGNAL_CRITICAL?'🔴 Critique':'🟢 Normal'}</p>`;
                document.getElementById('predictionsContainer').innerHTML = `<p>${temp>27?'⚠️ Tendance hausse température':'✅ Température stable'}</p><p>${signal<40?'⚠️ Dégradation signal possible':'✅ Signal stable'}</p>`;
                document.getElementById('recommendationsContainer').innerHTML = `<p>${temp>CONFIG.TEMP_WARNING?'🌡️ Surveiller température':'✅ Température normale'}</p><p>${signal<CONFIG.SIGNAL_WARNING?'📶 Vérifier réseau':'✅ Réseau optimal'}</p>`;
                
                await updateAlertChart();
            } catch (e) { console.error("Erreur analytics:", e); }
        }

        // ============================================
        // GÉNÉRATION PDF (INCHANGÉE - FONCTIONNELLE)
        // ============================================
        async function generatePDFReport() {
            const loadingDiv = document.getElementById('pdfLoading');
            if (loadingDiv) loadingDiv.style.display = 'block';
            try {
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF('p', 'mm', 'a4');
                const W=210, H=297;
                const username = "<?= htmlspecialchars($username) ?>";
                const currentTemp = document.getElementById('currentTemp')?.textContent || '--';
                const currentSignal = document.getElementById('currentSignal')?.textContent || '--';
                const aiScore = document.getElementById('aiScore')?.textContent || '--';
                const anomaly = document.getElementById('anomalyText')?.textContent || '--';
                const deviceStatus = document.getElementById('deviceStatus')?.textContent || '--';
                const scoreNum = parseFloat(aiScore) || 0;
                const tempNum = parseFloat(currentTemp) || 0;
                const signalNum = parseFloat(currentSignal) || 0;

                const addHeader = (t) => { doc.setFillColor(30,64,175); doc.rect(0,0,W,18,'F'); doc.setTextColor(255,255,255); doc.setFontSize(15); doc.text(t,15,12); };
                const addFooter = () => { for(let i=1;i<=doc.internal.getNumberOfPages();i++){ doc.setPage(i); doc.setTextColor(120); doc.setFontSize(8); doc.text(`Rapport Smart IoT Pro • Page ${i}/${doc.internal.getNumberOfPages()}`,15,292); } };
                const card = (x,y,w,h,c,t,v) => { doc.setFillColor(...c); doc.roundedRect(x,y,w,h,3,3,'F'); doc.setTextColor(255,255,255); doc.setFontSize(10); doc.text(t,x+4,y+7); doc.setFontSize(13); doc.text(String(v),x+4,y+16); };

                const qrDiv = document.createElement('div');
                new QRCode(qrDiv, { text: window.location.href, width:100, height:100 });
                await new Promise(r=>setTimeout(r,300));
                const qrImg = qrDiv.querySelector('img')?.src || qrDiv.querySelector('canvas')?.toDataURL();

                // Page 1
                doc.setFillColor(15,23,42); doc.rect(0,0,W,H,'F');
                doc.setFillColor(59,130,246); doc.circle(35,35,12,'F'); doc.setTextColor(255,255,255); doc.setFontSize(14); doc.text("IoT",30,38);
                doc.setFontSize(24); doc.text("SMART ANALYTICS PRO",55,55); doc.text("RAPPORT CORPORATE",50,72);
                doc.setDrawColor(96,165,250); doc.line(35,85,175,85);
                doc.setFontSize(11); doc.text(`Utilisateur: ${username}`,20,120); doc.text(`Généré: ${new Date().toLocaleString('fr-FR')}`,20,128); doc.text(`Appareil: ESP32`,20,136);
                if(qrImg) doc.addImage(qrImg,'PNG',145,110,35,35);

                // Page 2
                doc.addPage(); addHeader("Tableau de Bord"); let y=30;
                card(15,y,42,22,[249,115,22],"TEMP",currentTemp); card(62,y,42,22,[37,99,235],"SIGNAL",currentSignal); card(109,y,42,22,[139,92,246],"SCORE IA",aiScore); card(156,y,39,22,[16,185,129],"STATUT",deviceStatus);
                y+=35;

                // Page 3
                doc.addPage(); addHeader("Graphiques"); y=28;
                const hChart=document.getElementById('historyChart'), aChart=document.getElementById('alertChart'), pChart=document.getElementById('predictChart');
                doc.text("Historique",15,y); y+=5; if(hChart) doc.addImage(hChart.toDataURL('image/png'),'PNG',15,y,180,60); y+=70;
                doc.text("Alertes",15,y); y+=5; if(aChart) doc.addImage(aChart.toDataURL('image/png'),'PNG',15,y,180,55); y+=65;
                doc.text("Prédiction",15,y); y+=5; if(pChart) doc.addImage(pChart.toDataURL('image/png'),'PNG',15,y,180,45);

                // Page 4
                doc.addPage(); addHeader("Métriques Techniques"); y=30;
                doc.autoTable({ startY:y, head:[['Métrique','Valeur','Évaluation']], body:[
                    ['Température',currentTemp,tempNum>CONFIG.TEMP_CRITICAL?'Critique':tempNum>CONFIG.TEMP_WARNING?'Élevée':'Normale'],
                    ['Signal',currentSignal,signalNum<CONFIG.SIGNAL_CRITICAL?'Critique':signalNum<CONFIG.SIGNAL_WARNING?'Faible':'Stable'],
                    ['Score IA',aiScore,scoreNum>70?'Sain':scoreNum>40?'Attention':'Risque'],
                    ['Anomalie',anomaly,anomaly!=='Aucune'?'⚠️ Active':'✅ Aucune']
                ], theme:'grid', headStyles:{fillColor:[30,64,175]} });
                y=doc.lastAutoTable.finalY+40;
                doc.line(30,y,90,y); doc.text("Superviseur Technique",42,y+8);
                doc.line(120,y,180,y); doc.text("Signature Système",137,y+8);

                // Page 5
                doc.addPage(); addHeader("Conclusion IA"); y=35;
                let conclusion = scoreNum>=80 ? "Système optimal. Aucune action requise." : scoreNum>=50 ? "Instabilité modérée. Maintenance recommandée." : "Anomalies critiques. Intervention immédiate requise.";
                doc.setTextColor(0); doc.setFontSize(12); doc.text(doc.splitTextToSize(conclusion,170),15,y);
                y+=40;
                const insights=document.getElementById('insightsContainer')?.innerText||'', pred=document.getElementById('predictionsContainer')?.innerText||'', rec=document.getElementById('recommendationsContainer')?.innerText||'';
                doc.setFontSize(11); doc.setTextColor(30,64,175); doc.text("Aperçus:",15,y); doc.setTextColor(0); doc.setFontSize(10); doc.text(doc.splitTextToSize(insights,170),15,y+8);
                y+=30; doc.setTextColor(30,64,175); doc.setFontSize(11); doc.text("Prédictions:",15,y); doc.setTextColor(0); doc.setFontSize(10); doc.text(doc.splitTextToSize(pred,170),15,y+8);
                y+=30; doc.setTextColor(30,64,175); doc.setFontSize(11); doc.text("Recommandations:",15,y); doc.setTextColor(0); doc.setFontSize(10); doc.text(doc.splitTextToSize(rec,170),15,y+8);
                addFooter();
                doc.save(`rapport-smart-analytics-${Date.now()}.pdf`);
            } catch(e) { console.error("Erreur PDF:",e); alert("Échec génération PDF."); }
            finally { if(loadingDiv) loadingDiv.style.display='none'; }
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
