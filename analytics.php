<?php
session_start();
$user_id = $_SESSION['user_id'] ?? 1;
$username = $_SESSION['username'] ?? 'Utilisateur';
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
    // Configuration
    const CONFIG = {
        UPDATE_INTERVAL: 5000,
        OFFLINE_THRESHOLD: 30000,
        MAX_HISTORY_POINTS: 100,
        MAX_ALERT_POINTS: 50,
        TEMP_THRESHOLDS: {
            CRITICAL: 28,
            WARNING: 24
        },
        SIGNAL_THRESHOLDS: {
            CRITICAL: 30,
            WEAK: 50
        }
    };

    // État de l'application
    const state = {
        lastESP32Update: Date.now(),
        tempHistory: [],
        signalHistory: [],
        alerts: [],
        charts: {},
        currentHistoryView: '24h',
        alertCount: 0,
        lastPredictions: {}
    };

    // Initialisation des graphiques
    function initCharts() {
        // Graphique tendance température
        const tempCtx = document.getElementById('tempTrendChart').getContext('2d');
        state.charts.temp = new Chart(tempCtx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Température (°C)',
                    data: [],
                    borderColor: '#F59E0B',
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { 
                        beginAtZero: false,
                        grid: { color: 'rgba(0,0,0,0.05)' }
                    }
                }
            }
        });

        // Graphique tendance signal
        const signalCtx = document.getElementById('signalTrendChart').getContext('2d');
        state.charts.signal = new Chart(signalCtx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Signal (%)',
                    data: [],
                    borderColor: '#3B82F6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { 
                        beginAtZero: true,
                        max: 100,
                        grid: { color: 'rgba(0,0,0,0.05)' }
                    }
                }
            }
        });

        // Courbe d'historique combinée
        const historyCtx = document.getElementById('historyChart').getContext('2d');
        state.charts.history = new Chart(historyCtx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [
                    {
                        label: 'Température (°C)',
                        data: [],
                        borderColor: '#F59E0B',
                        backgroundColor: 'rgba(245, 158, 11, 0.1)',
                        tension: 0.4,
                        fill: false,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Signal (%)',
                        data: [],
                        borderColor: '#3B82F6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4,
                        fill: false,
                        yAxisID: 'y1'
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
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Température (°C)'
                        },
                        grid: { color: 'rgba(0,0,0,0.05)' }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Signal (%)'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });

        // Courbe d'alerte
        const alertCtx = document.getElementById('alertChart').getContext('2d');
        state.charts.alert = new Chart(alertCtx, {
            type: 'bar',
            data: {
                labels: [],
                datasets: [{
                    label: 'Alertes',
                    data: [],
                    backgroundColor: [],
                    borderColor: [],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Nombre d\'alertes'
                        }
                    }
                }
            }
        });
    }

    // Fonction Smart AI pour mettre à jour les insights
    function updateSmartAI(temperature, signal) {
        updateInsights(temperature, signal);
        updatePredictions(temperature, signal);
        updateRecommendations(temperature, signal);
    }

    // Mise à jour des insights en temps réel
    function updateInsights(temperature, signal) {
        const container = document.getElementById('insightsContainer');
        let insights = [];
        
        // Analyse de température
        if (temperature > CONFIG.TEMP_THRESHOLDS.CRITICAL) {
            insights.push({
                type: 'danger',
                text: `🔴 Température critique détectée (${temperature.toFixed(1)}°C)`
            });
        } else if (temperature > CONFIG.TEMP_THRESHOLDS.WARNING) {
            insights.push({
                type: 'warning',
                text: `🟡 Température en hausse (${temperature.toFixed(1)}°C)`
            });
        } else {
            insights.push({
                type: 'success',
                text: `🟢 Température normale (${temperature.toFixed(1)}°C)`
            });
        }
        
        // Analyse de signal
        if (signal < CONFIG.SIGNAL_THRESHOLDS.CRITICAL) {
            insights.push({
                type: 'danger',
                text: `🔴 Signal critique (${signal}%)`
            });
        } else if (signal < CONFIG.SIGNAL_THRESHOLDS.WEAK) {
            insights.push({
                type: 'warning',
                text: `🟡 Signal faible (${signal}%)`
            });
        } else {
            insights.push({
                type: 'success',
                text: `🟢 Signal optimal (${signal}%)`
            });
        }
        
        // Tendance
        if (state.tempHistory.length > 1) {
            const lastTemps = state.tempHistory.slice(-5);
            const trend = lastTemps[lastTemps.length - 1] - lastTemps[0];
            if (Math.abs(trend) > 0.5) {
                insights.push({
                    type: 'info',
                    text: `📈 Tendance: ${trend > 0 ? 'Hausse' : 'Baisse'} de ${Math.abs(trend).toFixed(1)}°C`
                });
            }
        }
        
        // Rendu
        container.innerHTML = insights.map(insight => 
            `<div class="flex items-start space-x-2 p-2 rounded ${
                insight.type === 'danger' ? 'bg-red-50' : 
                insight.type === 'warning' ? 'bg-yellow-50' : 
                insight.type === 'success' ? 'bg-green-50' : 'bg-blue-50'
            }">
                <span>${insight.text}</span>
            </div>`
        ).join('');
    }

    // Mise à jour des prédictions
    function updatePredictions(temperature, signal) {
        const container = document.getElementById('predictionsContainer');
        let predictions = [];
        
        // Prédiction de température
        if (state.tempHistory.length >= 5) {
            const recentTemps = state.tempHistory.slice(-5);
            const avgTemp = recentTemps.reduce((a, b) => a + b, 0) / recentTemps.length;
            const trend = (recentTemps[recentTemps.length - 1] - recentTemps[0]) / 5;
            const predictedTemp = avgTemp + trend * 10;
            
            predictions.push({
                icon: '🌡️',
                text: `Température prévue: ${predictedTemp.toFixed(1)}°C dans 10 min`
            });
            
            if (predictedTemp > CONFIG.TEMP_THRESHOLDS.CRITICAL) {
                predictions.push({
                    icon: '⚠️',
                    text: 'Alerte: Risque de surchauffe imminente',
                    type: 'danger'
                });
            }
        }
        
        // Prédiction de signal
        if (state.signalHistory.length >= 5) {
            const recentSignals = state.signalHistory.slice(-5);
            const avgSignal = recentSignals.reduce((a, b) => a + b, 0) / recentSignals.length;
            
            predictions.push({
                icon: '📶',
                text: `Signal moyen prévu: ${avgSignal.toFixed(0)}%`
            });
            
            if (avgSignal < CONFIG.SIGNAL_THRESHOLDS.WEAK) {
                predictions.push({
                    icon: '⚠️',
                    text: 'Attention: Dégradation du signal probable',
                    type: 'warning'
                });
            }
        }
        
        // Stabilité du système
        const uptime = Date.now() - state.lastESP32Update;
        const stabilityScore = Math.max(0, 100 - (uptime / (CONFIG.OFFLINE_THRESHOLD * 2) * 100));
        predictions.push({
            icon: '🔧',
            text: `Score de stabilité: ${stabilityScore.toFixed(0)}%`
        });
        
        state.lastPredictions = { temperature: temperature, signal: signal, predictions: predictions };
        
        container.innerHTML = predictions.map(pred => 
            `<div class="flex items-start space-x-2 p-2 rounded ${
                pred.type === 'danger' ? 'bg-red-50' : 
                pred.type === 'warning' ? 'bg-yellow-50' : 'bg-blue-50'
            }">
                <span>${pred.icon}</span>
                <span>${pred.text}</span>
            </div>`
        ).join('');
    }

    // Mise à jour des recommandations
    function updateRecommendations(temperature, signal) {
        const container = document.getElementById('recommendationsContainer');
        let recommendations = [];
        
        // Recommandations basées sur la température
        if (temperature > CONFIG.TEMP_THRESHOLDS.CRITICAL) {
            recommendations.push({
                priority: 'high',
                text: '🚨 ACTION IMMÉDIATE: Réduire la charge système',
                action: 'Activer le refroidissement d\'urgence'
            });
            recommendations.push({
                priority: 'high',
                text: '📋 Vérifier les systèmes de ventilation',
                action: 'Inspection recommandée'
            });
        } else if (temperature > CONFIG.TEMP_THRESHOLDS.WARNING) {
            recommendations.push({
                priority: 'medium',
                text: '⚡ Optimiser la consommation énergétique',
                action: 'Réduire les processus non essentiels'
            });
        } else {
            recommendations.push({
                priority: 'low',
                text: '✅ Système fonctionne de manière optimale',
                action: 'Maintenir la configuration actuelle'
            });
        }
        
        // Recommandations basées sur le signal
        if (signal < CONFIG.SIGNAL_THRESHOLDS.CRITICAL) {
            recommendations.push({
                priority: 'high',
                text: '📡 Vérifier la connexion antenne',
                action: 'Inspection matérielle requise'
            });
        } else if (signal < CONFIG.SIGNAL_THRESHOLDS.WEAK) {
            recommendations.push({
                priority: 'medium',
                text: '🔄 Optimiser la position de l\'antenne',
                action: 'Ajustement recommandé'
            });
        }
        
        // Recommandation de maintenance préventive
        const uptime = Date.now() - state.lastESP32Update;
        if (uptime > CONFIG.OFFLINE_THRESHOLD * 0.8) {
            recommendations.push({
                priority: 'medium',
                text: '🔧 Maintenance préventive recommandée',
                action: 'Planifier une vérification'
            });
        }
        
        container.innerHTML = recommendations.map(rec => 
            `<div class="flex items-start space-x-2 p-2 rounded ${
                rec.priority === 'high' ? 'bg-red-50 border-l-4 border-red-500' : 
                rec.priority === 'medium' ? 'bg-yellow-50 border-l-4 border-yellow-500' : 
                'bg-green-50 border-l-4 border-green-500'
            }">
                <div>
                    <div class="font-semibold">${rec.text}</div>
                    <div class="text-xs text-gray-600 mt-1">→ ${rec.action}</div>
                </div>
            </div>`
        ).join('');
    }

    // Génération du rapport PDF
    async function generatePDFReport() {
        // Afficher le loading
        document.getElementById('pdfLoading').style.display = 'block';
        
        try {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            
            // En-tête du rapport
            doc.setFontSize(22);
            doc.setTextColor(139, 92, 246);
            doc.text('Rapport Smart Analytics', 20, 20);
            
            doc.setFontSize(12);
            doc.setTextColor(100, 100, 100);
            doc.text(`Généré le: ${new Date().toLocaleString()}`, 20, 30);
            doc.text(`Utilisateur: <?php echo htmlspecialchars($username); ?>`, 20, 37);
            
            // Ligne de séparation
            doc.setDrawColor(139, 92, 246);
            doc.setLineWidth(0.5);
            doc.line(20, 42, 190, 42);
            
            // Section Statut
            doc.setFontSize(16);
            doc.setTextColor(0, 0, 0);
            doc.text('Statut du Système', 20, 55);
            
            const deviceStatus = document.getElementById('deviceStatus').textContent;
            const lastSeen = document.getElementById('lastSeen').textContent;
            const currentTemp = document.getElementById('currentTemp').textContent;
            const currentSignal = document.getElementById('currentSignal').textContent;
            
            doc.setFontSize(11);
            doc.text(`État: ${deviceStatus}`, 25, 65);
            doc.text(`Dernière activité: ${lastSeen}`, 25, 72);
            doc.text(`Température: ${currentTemp}`, 25, 79);
            doc.text(`Signal: ${currentSignal}`, 25, 86);
            
            // Section Insights
            doc.setFontSize(16);
            doc.text('Insights IA', 20, 100);
            
            const insights = document.getElementById('insightsContainer').innerText;
            const splitInsights = doc.splitTextToSize(insights, 170);
            doc.setFontSize(10);
            doc.text(splitInsights, 25, 110);
            
            // Section Prédictions
            const predictionsY = 110 + (splitInsights.length * 5);
            doc.setFontSize(16);
            doc.text('Prédictions', 20, predictionsY + 10);
            
            const predictions = document.getElementById('predictionsContainer').innerText;
            const splitPredictions = doc.splitTextToSize(predictions, 170);
            doc.setFontSize(10);
            doc.text(splitPredictions, 25, predictionsY + 20);
            
            // Section Recommandations
            const recommendationsY = predictionsY + 20 + (splitPredictions.length * 5);
            doc.setFontSize(16);
            doc.text('Recommandations', 20, recommendationsY + 10);
            
            const recommendations = document.getElementById('recommendationsContainer').innerText;
            const splitRecommendations = doc.splitTextToSize(recommendations, 170);
            doc.setFontSize(10);
            doc.text(splitRecommendations, 25, recommendationsY + 20);
            
            // Ajouter les graphiques comme images
            if (state.charts.history) {
                const historyCanvas = document.getElementById('historyChart');
                const historyImage = historyCanvas.toDataURL('image/png');
                
                if (recommendationsY + 40 + (splitRecommendations.length * 5) > 250) {
                    doc.addPage();
                }
                
                doc.setFontSize(16);
                doc.text('Graphiques', 20, recommendationsY + 40 + (splitRecommendations.length * 5));
                doc.addImage(historyImage, 'PNG', 20, recommendationsY + 45 + (splitRecommendations.length * 5), 170, 60);
            }
            
            // Pied de page
            const pageCount = doc.internal.getNumberOfPages();
            for(let i = 1; i <= pageCount; i++) {
                doc.setPage(i);
                doc.setFontSize(8);
                doc.setTextColor(150, 150, 150);
                doc.text(`Page ${i} sur ${pageCount} - Smart Analytics © ${new Date().getFullYear()}`, 105, 290, { align: 'center' });
            }
            
            // Sauvegarder le PDF
            doc.save(`rapport-analytics-${new Date().toISOString().split('T')[0]}.pdf`);
            
        } catch (error) {
            console.error('Erreur lors de la génération du PDF:', error);
            alert('Erreur lors de la génération du rapport PDF. Veuillez réessayer.');
        } finally {
            // Cacher le loading
            document.getElementById('pdfLoading').style.display = 'none';
        }
    }

    // Mise à jour de la courbe d'historique
    function updateHistoryChart(temperature, signal) {
        const chart = state.charts.history;
        const now = new Date().toLocaleTimeString();
        
        chart.data.labels.push(now);
        chart.data.datasets[0].data.push(temperature);
        chart.data.datasets[1].data.push(signal);
        
        // Limiter le nombre de points selon la vue
        const maxPoints = state.currentHistoryView === '1h' ? 12 : 
                         state.currentHistoryView === '24h' ? 288 : 2016;
        
        while (chart.data.labels.length > maxPoints) {
            chart.data.labels.shift();
            chart.data.datasets[0].data.shift();
            chart.data.datasets[1].data.shift();
        }
        
        // Mettre à jour les statistiques
        updateHistoryStats();
        
        chart.update();
    }

    // Mise à jour des statistiques d'historique
    function updateHistoryStats() {
        const tempData = state.charts.history.data.datasets[0].data;
        const signalData = state.charts.history.data.datasets[1].data;
        
        if (tempData.length > 0) {
            document.getElementById('historyMin').textContent = 
                `${Math.min(...tempData).toFixed(1)}°C / ${Math.min(...signalData)}%`;
            document.getElementById('historyMax').textContent = 
                `${Math.max(...tempData).toFixed(1)}°C / ${Math.max(...signalData)}%`;
            document.getElementById('historyAvg').textContent = 
                `${(tempData.reduce((a,b) => a+b, 0) / tempData.length).toFixed(1)}°C / ${(signalData.reduce((a,b) => a+b, 0) / signalData.length).toFixed(0)}%`;
        }
    }

    // Changement de vue d'historique
    function switchHistoryView(view) {
        state.currentHistoryView = view;
        
        // Mettre à jour les boutons
        const buttons = document.querySelectorAll('.grid .flex.space-x-2 button');
        buttons.forEach(btn => {
            btn.classList.remove('bg-blue-100');
            btn.classList.add('bg-gray-100');
        });
        
        const activeButton = Array.from(buttons).find(btn => 
            btn.textContent.toLowerCase().includes(view)
        );
        if (activeButton) {
            activeButton.classList.remove('bg-gray-100');
            activeButton.classList.add('bg-blue-100');
        }
    }

    // Ajout d'une alerte
    function addAlert(type, severity, message) {
        const alert = {
            timestamp: new Date(),
            type: type,
            severity: severity,
            message: message
        };
        
        state.alerts.push(alert);
        state.alertCount++;
        
        // Mettre à jour le compteur d'alertes
        const alertBadge = document.getElementById('alertCount');
        if (alertBadge) {
            alertBadge.textContent = state.alertCount;
            alertBadge.classList.remove('hidden');
        }
        
        // Mettre à jour le graphique d'alertes
        updateAlertChart();
    }

    // Mise à jour de la courbe d'alerte
    function updateAlertChart() {
        const chart = state.charts.alert;
        const filter = document.getElementById('alertTypeFilter').value;
        
        // Filtrer les alertes
        let filteredAlerts = state.alerts;
        if (filter !== 'all') {
            filteredAlerts = state.alerts.filter(a => a.type === filter);
        }
        
        // Grouper par heure
        const hourlyAlerts = {};
        filteredAlerts.forEach(alert => {
            const hour = alert.timestamp.getHours() + ':00';
            hourlyAlerts[hour] = (hourlyAlerts[hour] || 0) + 1;
        });
        
        // Préparer les données
        const labels = Object.keys(hourlyAlerts).sort();
        const data = labels.map(label => hourlyAlerts[label]);
        const colors = labels.map(() => '#EF4444');
        
        chart.data.labels = labels;
        chart.data.datasets[0].data = data;
        chart.data.datasets[0].backgroundColor = colors;
        chart.data.datasets[0].borderColor = colors;
        
        // Mettre à jour le compteur du jour
        const todayAlerts = state.alerts.filter(a => {
            const today = new Date();
            return a.timestamp.toDateString() === today.toDateString();
        }).length;
        document.getElementById('todayAlerts').textContent = todayAlerts;
        
        chart.update();
    }

    // Filtrage des alertes
    function filterAlerts() {
        updateAlertChart();
    }

    // Vérification et génération d'alertes
    function checkAlerts(temperature, signal) {
        // Vérification température
        if (temperature > CONFIG.TEMP_THRESHOLDS.CRITICAL) {
            addAlert('temperature', 'critical', 
                `Température critique: ${temperature.toFixed(1)}°C`);
        } else if (temperature > CONFIG.TEMP_THRESHOLDS.WARNING) {
            addAlert('temperature', 'warning', 
                `Température élevée: ${temperature.toFixed(1)}°C`);
        }
        
        // Vérification signal
        if (signal < CONFIG.SIGNAL_THRESHOLDS.CRITICAL) {
            addAlert('signal', 'critical', 
                `Signal critique: ${signal}%`);
        } else if (signal < CONFIG.SIGNAL_THRESHOLDS.WEAK) {
            addAlert('signal', 'warning', 
                `Signal faible: ${signal}%`);
        }
    }

    // Mise à jour des graphiques de tendance
    function updateChart(chart, label, value, maxPoints = 20) {
        const now = new Date().toLocaleTimeString();
        
        chart.data.labels.push(now);
        chart.data.datasets[0].data.push(value);
        
        if (chart.data.labels.length > maxPoints) {
            chart.data.labels.shift();
            chart.data.datasets[0].data.shift();
        }
        
        chart.update();
    }

    // Vérification du statut hors ligne
    function checkOffline() {
        const diff = Date.now() - state.lastESP32Update;
        const deviceStatus = document.getElementById('deviceStatus');
        const statusIcon = document.getElementById('deviceStatusIcon');
        const globalIndicator = document.getElementById('globalStatusIndicator');
        const globalStatus = document.getElementById('globalStatus');
        const liveStatusText = document.getElementById('liveStatusText');

        if (diff > CONFIG.OFFLINE_THRESHOLD) {
            deviceStatus.textContent = 'Hors ligne';
            deviceStatus.className = 'font-semibold status-offline';
            statusIcon.textContent = '🔴';
            if (globalIndicator) {
                globalIndicator.className = 'w-3 h-3 rounded-full bg-red-500 pulse-animation';
            }
            if (globalStatus) {
                globalStatus.textContent = 'Déconnecté';
                globalStatus.className = 'font-semibold status-offline';
            }
            if (liveStatusText) {
                liveStatusText.textContent = 'Offline';
                liveStatusText.className = 'text-red-600';
            }
            
            // Ajouter une alerte de connexion si nécessaire
            if (diff > CONFIG.OFFLINE_THRESHOLD && diff < CONFIG.OFFLINE_THRESHOLD + CONFIG.UPDATE_INTERVAL) {
                addAlert('connection', 'critical', 'Appareil hors ligne');
            }
        } else {
            deviceStatus.textContent = 'En ligne';
            deviceStatus.className = 'font-semibold status-online';
            statusIcon.textContent = '📡';
            if (globalIndicator) {
                globalIndicator.className = 'w-3 h-3 rounded-full bg-green-500';
            }
            if (globalStatus) {
                globalStatus.textContent = 'Connecté';
                globalStatus.className = 'font-semibold status-online';
            }
            if (liveStatusText) {
                liveStatusText.textContent = 'Real-time';
                liveStatusText.className = 'text-green-600';
            }
        }
    }

    // Analyse de la température
    function analyzeTemperature(temp) {
        const tempTrend = document.getElementById('tempTrend');
        const tempIcon = document.getElementById('tempIcon');
        const currentTemp = document.getElementById('currentTemp');
        
        currentTemp.textContent = `${temp.toFixed(1)}°C`;
        
        let status = "Stable";
        let colorClass = "status-good";
        let icon = "🌡️";
        
        if (temp > CONFIG.TEMP_THRESHOLDS.CRITICAL) {
            status = "Critique";
            colorClass = "status-critical";
            icon = "🔥";
        } else if (temp > CONFIG.TEMP_THRESHOLDS.WARNING) {
            status = "Élevée";
            colorClass = "status-warning";
            icon = "🌡️";
        }
        
        tempTrend.textContent = status;
        tempTrend.className = `font-semibold ${colorClass}`;
        tempIcon.textContent = icon;
        
        updateChart(state.charts.temp, 'Température', temp);
    }

    // Analyse du signal
    function analyzeSignal(signal) {
        const signalTrend = document.getElementById('signalTrend');
        const signalIcon = document.getElementById('signalIcon');
        const currentSignal = document.getElementById('currentSignal');
        
        currentSignal.textContent = `${signal}%`;
        
        let status = "Bon";
        let colorClass = "status-good";
        let icon = "📶";
        
        if (signal < CONFIG.SIGNAL_THRESHOLDS.CRITICAL) {
            status = "Critique";
            colorClass = "status-critical";
            icon = "📡";
        } else if (signal < CONFIG.SIGNAL_THRESHOLDS.WEAK) {
            status = "Faible";
            colorClass = "status-warning";
            icon = "📶";
        }
        
        signalTrend.textContent = status;
        signalTrend.className = `font-semibold ${colorClass}`;
        signalIcon.textContent = icon;
        
        updateChart(state.charts.signal, 'Signal', signal);
    }

    // Calcul et affichage de l'uptime
    function updateUptime() {
        const uptime = Date.now() - state.lastESP32Update;
        const seconds = Math.floor(uptime / 1000);
        const minutes = Math.floor(seconds / 60);
        const hours = Math.floor(minutes / 60);
        
        document.getElementById('uptime').textContent = 
            `${hours}h ${minutes % 60}m ${seconds % 60}s`;
    }

    // Chargement des données analytics
    async function loadAnalytics() {
        try {
            const userId = '<?= isset($user_id) ? $user_id : "" ?>';
            const response = await fetch(`get_latest_data.php?user_id=${userId}`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                state.lastESP32Update = Date.now();
                
                document.getElementById('lastSeen').textContent = 
                    new Date().toLocaleTimeString();
                
                const temperature = data.data.temperature || 0;
                const signal = data.data.signal_strength || 0;
                
                if (data.data.temperature !== undefined) {
                    analyzeTemperature(temperature);
                    state.tempHistory.push(temperature);
                }
                
                if (data.data.signal_strength !== undefined) {
                    analyzeSignal(signal);
                    state.signalHistory.push(signal);
                }
                
                // Mise à jour des courbes d'historique et d'alerte
                updateHistoryChart(temperature, signal);
                checkAlerts(temperature, signal);
                updateUptime();
                
                // Mise à jour de l'IA
                updateSmartAI(temperature, signal);
            }
        } catch (error) {
            console.error('Erreur lors du chargement des données:', error);
            document.getElementById('deviceStatus').textContent = 'Erreur';
            document.getElementById('deviceStatus').className = 'font-semibold status-offline';
        }
    }

    // Gestion des erreurs globales
    window.addEventListener('error', function(e) {
        console.error('Erreur globale:', e.error);
    });

    // Gestion des promesses non gérées
    window.addEventListener('unhandledrejection', function(e) {
        console.error('Promesse non gérée:', e.reason);
    });

    // Initialisation
    function init() {
        initCharts();
        loadAnalytics();
        
        // Intervalles de mise à jour
        setInterval(checkOffline, CONFIG.UPDATE_INTERVAL);
        setInterval(loadAnalytics, CONFIG.UPDATE_INTERVAL);
        setInterval(updateUptime, 1000);
        
        // Première vérification immédiate
        checkOffline();
    }

    // Démarrage de l'application
    document.addEventListener('DOMContentLoaded', init);
    </script>
</body>
</html>