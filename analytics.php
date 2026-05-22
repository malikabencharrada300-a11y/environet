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
<title>ENVIRONET Analytics - Real Time Dashboard</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
<style>
body {
    background: #f4f7fc;
    font-family: 'Segoe UI', Arial, sans-serif;
}
.card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    transition: transform 0.2s, box-shadow 0.2s;
}
.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.12);
}
.chart-box {
    height: 400px;
    position: relative;
}
.btn-primary {
    transition: all 0.3s ease;
}
.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.filter-btn {
    transition: all 0.2s ease;
    cursor: pointer;
}
.filter-btn.active {
    background-color: #3b82f6 !important;
    color: white !important;
}
.realtime-badge {
    animation: pulse 2s infinite;
}
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
}
.loading {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid #f3f3f3;
    border-top: 3px solid #3b82f6;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
.value-update {
    transition: all 0.3s ease;
}
.value-update.changed {
    transform: scale(1.1);
    color: #3b82f6;
}
.sensor-status {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    animation: pulse 1.5s infinite;
}
.sensor-status.online {
    background-color: #10b981;
    box-shadow: 0 0 5px #10b981;
}
.sensor-status.offline {
    background-color: #ef4444;
}
.sensor-status.demo {
    background-color: #f59e0b;
    box-shadow: 0 0 5px #f59e0b;
}
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}
.mode-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: bold;
}
.mode-real {
    background-color: #10b981;
    color: white;
}
.mode-demo {
    background-color: #f59e0b;
    color: white;
}
</style>
</head>
<body class="p-6">
<div class="max-w-7xl mx-auto">
    <!-- HEADER -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-blue-700">🌍 ENVIRONET Analytics</h1>
            <p class="text-gray-500 flex items-center gap-2">
                Real Time IoT Monitoring & Intelligence
                <span class="realtime-badge inline-flex items-center gap-1 text-xs bg-green-100 text-green-700 px-2 py-1 rounded-full">
                    <span class="w-2 h-2 bg-green-600 rounded-full animate-pulse"></span>
                    LIVE
                </span>
            </p>
        </div>
        <div class="flex gap-3">
            <a href="dashboard.php" class="bg-blue-600 text-white px-5 py-2 rounded-lg btn-primary flex items-center gap-2">
                ← Dashboard
            </a>
            <button onclick="generatePDF()" class="bg-purple-700 text-white px-5 py-2 rounded-lg btn-primary flex items-center gap-2">
                📄 PDF Report
            </button>
        </div>
    </div>

    <!-- STATS CARDS -->
    <div class="grid md:grid-cols-4 gap-5 mb-6">
        <!-- Device Status Card -->
        <div class="card">
            <div class="flex justify-between items-start">
                <h2 class="font-bold mb-4">📡 Device Status</h2>
                <span id="modeBadge" class="mode-badge mode-real">🟢 REAL</span>
            </div>
            <p class="text-gray-500 text-sm">System State</p>
            <p id="deviceStatus" class="text-2xl font-bold text-green-600">Online</p>
            
            <!-- DHT11 Sensor State -->
            <p class="text-gray-500 text-sm mt-3">🟢 DHT11 Sensor</p>
            <div class="flex items-center gap-2">
                <span id="dhtStatus" class="sensor-status online"></span>
                <span id="dhtStatusText" class="font-bold text-green-600">Connected</span>
            </div>
        </div>

        <div class="card">
            <h2 class="font-bold mb-4">🌡 Temperature</h2>
            <p id="currentTemp" class="text-4xl font-black text-orange-600 value-update">--°C</p>
            <p id="tempStatus" class="mt-2 font-bold text-green-600">Normal</p>
        </div>
        <div class="card">
            <h2 class="font-bold mb-4">💧 Humidity</h2>
            <p id="currentHumidity" class="text-4xl font-black text-blue-600 value-update">--%</p>
            <p id="humidityStatus" class="mt-2 font-bold text-blue-500">Normal</p>
        </div>
        <div class="card">
            <h2 class="font-bold mb-4">📶 Signal</h2>
            <p id="currentSignal" class="text-4xl font-black text-indigo-600 value-update">--%</p>
            <p id="signalStatus" class="mt-2 font-bold text-green-600">Excellent</p>
        </div>
    </div>

    <!-- AI SECTION -->
    <div class="card mb-6">
        <h2 class="text-2xl font-bold mb-5">🧠 AI Real-Time Analysis</h2>
        <div class="grid md:grid-cols-4 gap-4">
            <div class="bg-gray-50 rounded-xl p-4">
                <p class="text-gray-500 text-sm">AI Score</p>
                <p id="aiScore" class="text-3xl font-bold text-purple-700 value-update">--</p>
                <div class="w-full bg-gray-200 rounded-full mt-2 h-2">
                    <div id="aiScoreBar" class="bg-purple-600 rounded-full h-2 transition-all duration-500" style="width: 0%"></div>
                </div>
            </div>
            <div class="bg-gray-50 rounded-xl p-4">
                <p class="text-gray-500 text-sm">Health Status</p>
                <p id="aiHealth" class="text-3xl font-bold text-green-600">--</p>
            </div>
            <div class="bg-gray-50 rounded-xl p-4">
                <p class="text-gray-500 text-sm">Anomaly Detection</p>
                <p id="anomalyText" class="text-3xl font-bold text-orange-600">--</p>
            </div>
            <div class="bg-gray-50 rounded-xl p-4">
                <p class="text-gray-500 text-sm">Data Points</p>
                <p id="dataCount" class="text-3xl font-bold text-blue-600">0</p>
                <p class="text-xs text-gray-400 mt-1">Historical records</p>
            </div>
        </div>
    </div>

    <!-- CHARTS SECTION -->
    <div class="card mb-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="font-bold text-xl">📊 Complete Sensor History</h2>
            <div class="flex gap-2">
                <button onclick="loadSensorData('hour')" id="filterHour" class="filter-btn px-3 py-1 text-sm rounded-lg bg-gray-200 hover:bg-blue-500 hover:text-white transition">Hour</button>
                <button onclick="loadSensorData('day')" id="filterDay" class="filter-btn px-3 py-1 text-sm rounded-lg bg-blue-600 text-white active transition">Day</button>
                <button onclick="loadSensorData('week')" id="filterWeek" class="filter-btn px-3 py-1 text-sm rounded-lg bg-gray-200 hover:bg-blue-500 hover:text-white transition">Week</button>
                <button onclick="loadSensorData('month')" id="filterMonth" class="filter-btn px-3 py-1 text-sm rounded-lg bg-gray-200 hover:bg-blue-500 hover:text-white transition">Month</button>
            </div>
        </div>
        <div class="chart-box">
            <canvas id="sensorChart"></canvas>
        </div>
        <p class="text-xs text-gray-400 text-center mt-2">📈 Temperature (Orange) • 💧 Humidity (Cyan) • 📶 Signal (Blue) • Real data • Updates every 5s</p>
    </div>

    <div class="card">
        <div class="flex justify-between items-center mb-4">
            <h2 class="font-bold text-xl">⚠️ Alert Analysis</h2>
            <div class="flex gap-2">
                <button onclick="loadAlertData('hour')" id="filterAlertHour" class="filter-btn px-3 py-1 text-sm rounded-lg bg-gray-200 hover:bg-blue-500 hover:text-white transition">Hour</button>
                <button onclick="loadAlertData('day')" id="filterAlertDay" class="filter-btn px-3 py-1 text-sm rounded-lg bg-blue-600 text-white active transition">Day</button>
                <button onclick="loadAlertData('week')" id="filterAlertWeek" class="filter-btn px-3 py-1 text-sm rounded-lg bg-gray-200 hover:bg-blue-500 hover:text-white transition">Week</button>
                <button onclick="loadAlertData('month')" id="filterAlertMonth" class="filter-btn px-3 py-1 text-sm rounded-lg bg-gray-200 hover:bg-blue-500 hover:text-white transition">Month</button>
            </div>
        </div>
        <div class="chart-box">
            <canvas id="alertChart"></canvas>
        </div>
        <p class="text-xs text-gray-400 text-center mt-2">⚠️ Alert levels: 0=Normal, 1=Warning, 2=Critical • Real-time detection</p>
    </div>
</div>

<script>
// ============================================
// REAL TIME SENSOR DASHBOARD WITH COMBINED CHART
// ============================================

let sensorChart = null;
let alertChart = null;
let currentSensorPeriod = 'day';
let currentAlertPeriod = 'day';
let updateInterval = null;
let isRealMode = true;

// Configuration
const CONFIG = {
    TEMP_WARNING: 28,
    TEMP_CRITICAL: 35,
    SIGNAL_WARNING: 60,
    SIGNAL_CRITICAL: 30,
    UPDATE_INTERVAL: 5000,
    SENSOR_TIMEOUT: 30000
};

// Store alert history
let alertHistory = {
    timestamps: [],
    tempAlerts: [],
    signalAlerts: [],
    connectionAlerts: []
};

// ============================================
// INITIALIZE CHARTS
// ============================================

function initCharts() {
    const sensorCtx = document.getElementById('sensorChart').getContext('2d');
    sensorChart = new Chart(sensorCtx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [
                {
                    label: 'Temperature (°C)',
                    data: [],
                    borderColor: '#f97316',
                    backgroundColor: 'rgba(249,115,22,0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 3,
                    pointHoverRadius: 6,
                    yAxisID: 'y'
                },
                {
                    label: 'Humidity (%)',
                    data: [],
                    borderColor: '#06b6d4',
                    backgroundColor: 'rgba(6,182,212,0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 3,
                    pointHoverRadius: 6,
                    yAxisID: 'y'
                },
                {
                    label: 'Signal Strength (%)',
                    data: [],
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37,99,235,0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 3,
                    pointHoverRadius: 6,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: { usePointStyle: true, boxWidth: 10, font: { size: 12 } }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            let value = context.raw;
                            if (context.dataset.label === 'Temperature (°C)') {
                                return label + ': ' + value.toFixed(1) + '°C';
                            }
                            if (context.dataset.label === 'Humidity (%)') {
                                return label + ': ' + value.toFixed(1) + '%';
                            }
                            return label + ': ' + value.toFixed(0) + '%';
                        }
                    }
                }
            },
            scales: {
                y: {
                    title: { display: true, text: 'Temperature (°C) / Humidity (%)' },
                    min: 0,
                    max: 100,
                    grid: { color: 'rgba(0,0,0,0.05)' }
                },
                y1: {
                    position: 'right',
                    title: { display: true, text: 'Signal Strength (%)' },
                    min: 0,
                    max: 100,
                    grid: { drawOnChartArea: false }
                },
                x: {
                    title: { display: true, text: 'Timestamp' },
                    ticks: { maxRotation: 45, minRotation: 45 }
                }
            }
        }
    });

    const alertCtx = document.getElementById('alertChart').getContext('2d');
    alertChart = new Chart(alertCtx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [
                {
                    label: 'Temperature Alerts',
                    data: [],
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239,68,68,0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    stepped: true,
                    pointRadius: 4,
                    pointHoverRadius: 6
                },
                {
                    label: 'Signal Alerts',
                    data: [],
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245,158,11,0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    stepped: true,
                    pointRadius: 4,
                    pointHoverRadius: 6
                },
                {
                    label: 'Connection Alerts',
                    data: [],
                    borderColor: '#8b5cf6',
                    backgroundColor: 'rgba(139,92,246,0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    stepped: true,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top', labels: { usePointStyle: true, boxWidth: 10 } },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const value = context.raw;
                            let status = '';
                            if (value === 0) status = '✓ Normal';
                            else if (value === 1) status = '⚠ Warning';
                            else if (value === 2) status = '🔴 Critical';
                            return context.dataset.label + ': ' + status;
                        }
                    }
                }
            },
            scales: {
                y: {
                    title: { display: true, text: 'Alert Level' },
                    min: -0.5,
                    max: 2.5,
                    ticks: {
                        stepSize: 0.5,
                        callback: function(value) {
                            if (value === 0) return 'Normal';
                            if (value === 1) return 'Warning';
                            if (value === 2) return 'Critical';
                            return '';
                        }
                    }
                },
                x: { title: { display: true, text: 'Timestamp' }, ticks: { maxRotation: 45, minRotation: 45 } }
            }
        }
    });
}

// ============================================
// UPDATE MODE DISPLAY
// ============================================

function updateModeDisplay(isReal) {
    const modeBadge = document.getElementById('modeBadge');
    const dhtStatusDot = document.getElementById('dhtStatus');
    const dhtStatusText = document.getElementById('dhtStatusText');
    
    if (isReal) {
        modeBadge.className = 'mode-badge mode-real';
        modeBadge.innerHTML = '🟢 REAL';
        dhtStatusDot.className = 'sensor-status online';
        dhtStatusText.innerHTML = 'Connected ✅';
        dhtStatusText.className = 'font-bold text-green-600';
    } else {
        modeBadge.className = 'mode-badge mode-demo';
        modeBadge.innerHTML = '🟡 DEMO';
        dhtStatusDot.className = 'sensor-status demo';
        dhtStatusText.innerHTML = 'Demo Mode';
        dhtStatusText.className = 'font-bold text-yellow-600';
    }
}

// ============================================
// UPDATE DHT11 SENSOR STATUS
// ============================================

function updateDHT11Status(isOnline) {
    const dhtStatusDot = document.getElementById('dhtStatus');
    const dhtStatusText = document.getElementById('dhtStatusText');
    
    if (isOnline && isRealMode) {
        dhtStatusDot.className = 'sensor-status online';
        dhtStatusText.innerHTML = 'Connected ✅';
        dhtStatusText.className = 'font-bold text-green-600';
    } else if (!isOnline && isRealMode) {
        dhtStatusDot.className = 'sensor-status offline';
        dhtStatusText.innerHTML = 'Disconnected ❌';
        dhtStatusText.className = 'font-bold text-red-600';
    } else {
        dhtStatusDot.className = 'sensor-status demo';
        dhtStatusText.innerHTML = 'Demo Mode';
        dhtStatusText.className = 'font-bold text-yellow-600';
    }
}

// ============================================
// LOAD COMBINED SENSOR DATA
// ============================================

async function loadSensorData(period) {
    currentSensorPeriod = period;
    
    document.querySelectorAll('[id^="filter"][id$="Hour"],[id^="filter"][id$="Day"],[id^="filter"][id$="Week"],[id^="filter"][id$="Month"]').forEach(btn => {
        if (!btn.id.includes('Alert')) {
            btn.classList.remove('active', 'bg-blue-600', 'text-white');
            btn.classList.add('bg-gray-200');
        }
    });
    
    const btnMap = { 'hour': 'filterHour', 'day': 'filterDay', 'week': 'filterWeek', 'month': 'filterMonth' };
    const activeBtn = document.getElementById(btnMap[period]);
    if (activeBtn) {
        activeBtn.classList.remove('bg-gray-200');
        activeBtn.classList.add('active', 'bg-blue-600', 'text-white');
    }
    
    let limit = 24;
    if (period === 'hour') limit = 60;
    else if (period === 'day') limit = 24;
    else if (period === 'week') limit = 168;
    else if (period === 'month') limit = 720;
    
    try {
        const response = await fetch(`get_history.php?period=${period}&limit=${limit}&user_id=<?= $user_id ?>`);
        const result = await response.json();
        
        if (result.success && result.data) {
            if (sensorChart) {
                sensorChart.data.labels = result.data.timestamps || [];
                sensorChart.data.datasets[0].data = result.data.temperatures || [];
                sensorChart.data.datasets[1].data = result.data.humidities || [];
                sensorChart.data.datasets[2].data = result.data.signals || [];
                sensorChart.update();
            }
            const dataCount = (result.data.temperatures || []).length;
            document.getElementById('dataCount').innerHTML = dataCount;
        } else {
            generateDemoSensorData(period, limit);
        }
    } catch(error) {
        generateDemoSensorData(period, limit);
    }
}

function generateDemoSensorData(period, limit) {
    const now = new Date();
    const labels = [];
    const temps = [];
    const humidities = [];
    const signals = [];
    
    for (let i = limit; i >= 0; i--) {
        let date = new Date(now);
        if (period === 'hour') date.setMinutes(now.getMinutes() - i);
        else date.setHours(now.getHours() - i);
        
        labels.push(date.toLocaleTimeString());
        
        const baseTemp = 22 + Math.sin(i / 10) * 5 + Math.random() * 2;
        const baseHumidity = 55 + Math.cos(i / 8) * 15 + Math.random() * 5;
        const baseSignal = 75 + Math.sin(i / 20) * 20 + Math.random() * 3;
        
        temps.push(baseTemp);
        humidities.push(Math.min(100, Math.max(0, baseHumidity)));
        signals.push(Math.min(100, Math.max(0, baseSignal)));
    }
    
    if (sensorChart) {
        sensorChart.data.labels = labels;
        sensorChart.data.datasets[0].data = temps;
        sensorChart.data.datasets[1].data = humidities;
        sensorChart.data.datasets[2].data = signals;
        sensorChart.update();
    }
    document.getElementById('dataCount').innerHTML = limit;
}

// ============================================
// LOAD ALERT DATA
// ============================================

async function loadAlertData(period) {
    currentAlertPeriod = period;
    
    document.querySelectorAll('[id^="filterAlert"]').forEach(btn => {
        btn.classList.remove('active', 'bg-blue-600', 'text-white');
        btn.classList.add('bg-gray-200');
    });
    
    const btnMap = { 'hour': 'filterAlertHour', 'day': 'filterAlertDay', 'week': 'filterAlertWeek', 'month': 'filterAlertMonth' };
    const activeBtn = document.getElementById(btnMap[period]);
    if (activeBtn) {
        activeBtn.classList.remove('bg-gray-200');
        activeBtn.classList.add('active', 'bg-blue-600', 'text-white');
    }
    
    let limit = 24;
    if (period === 'hour') limit = 60;
    else if (period === 'day') limit = 24;
    else if (period === 'week') limit = 168;
    else if (period === 'month') limit = 720;
    
    try {
        const response = await fetch(`get_alerts.php?period=${period}&limit=${limit}&user_id=<?= $user_id ?>`);
        const result = await response.json();
        
        if (result.success && result.data) {
            if (alertChart) {
                alertChart.data.labels = result.data.timestamps || [];
                alertChart.data.datasets[0].data = result.data.tempAlerts || [];
                alertChart.data.datasets[1].data = result.data.signalAlerts || [];
                alertChart.data.datasets[2].data = result.data.connectionAlerts || [];
                alertChart.update();
            }
        } else {
            generateDemoAlertData(period, limit);
        }
    } catch(error) {
        generateDemoAlertData(period, limit);
    }
}

function generateDemoAlertData(period, limit) {
    const now = new Date();
    const labels = [];
    const tempAlerts = [];
    const signalAlerts = [];
    const connectionAlerts = [];
    
    for (let i = limit; i >= 0; i--) {
        let date = new Date(now);
        if (period === 'hour') date.setMinutes(now.getMinutes() - i);
        else date.setHours(now.getHours() - i);
        
        labels.push(date.toLocaleTimeString());
        
        let tempAlert = 0;
        let signalAlert = 0;
        let connAlert = 0;
        
        const rand = Math.random();
        const hour = date.getHours();
        
        if (hour > 12 && hour < 16) {
            tempAlert = rand > 0.85 ? 2 : (rand > 0.7 ? 1 : 0);
        } else {
            tempAlert = rand > 0.95 ? 1 : 0;
        }
        
        signalAlert = rand > 0.9 ? 1 : 0;
        if (rand > 0.97) signalAlert = 2;
        connAlert = rand > 0.98 ? 1 : 0;
        
        tempAlerts.push(tempAlert);
        signalAlerts.push(signalAlert);
        connectionAlerts.push(connAlert);
        
        alertHistory.timestamps.push(labels[labels.length-1]);
        alertHistory.tempAlerts.push(tempAlert);
        alertHistory.signalAlerts.push(signalAlert);
        alertHistory.connectionAlerts.push(connAlert);
        
        if (alertHistory.timestamps.length > 100) {
            alertHistory.timestamps.shift();
            alertHistory.tempAlerts.shift();
            alertHistory.signalAlerts.shift();
            alertHistory.connectionAlerts.shift();
        }
    }
    
    if (alertChart) {
        alertChart.data.labels = labels;
        alertChart.data.datasets[0].data = tempAlerts;
        alertChart.data.datasets[1].data = signalAlerts;
        alertChart.data.datasets[2].data = connectionAlerts;
        alertChart.update();
    }
}

// ============================================
// REAL TIME DATA WITH ALERT DETECTION
// ============================================

async function loadLatestData() {
    try {
        const response = await fetch(`get_latest_data.php?user_id=<?= $user_id ?>&t=${Date.now()}`);
        const result = await response.json();
        
        if (result.success && result.data) {
            // Mode RÉEL
            isRealMode = true;
            updateModeDisplay(true);
            
            const data = result.data;
            const temp = parseFloat(data.temperature);
            const humidity = parseFloat(data.humidity);
            const signal = parseFloat(data.signal_strength);
            
            updateDHT11Status(true);
            document.getElementById('deviceStatus').innerHTML = 'Online';
            document.getElementById('deviceStatus').className = 'text-2xl font-bold text-green-600';
            
            animateValue('currentTemp', temp.toFixed(1) + '°C');
            animateValue('currentHumidity', humidity.toFixed(1) + '%');
            animateValue('currentSignal', signal.toFixed(0) + '%');
            
            let tempAlert = 0;
            if (temp >= CONFIG.TEMP_CRITICAL) {
                tempAlert = 2;
                updateStatus('tempStatus', 'Critical 🔴', 'text-red-600');
            } else if (temp >= CONFIG.TEMP_WARNING) {
                tempAlert = 1;
                updateStatus('tempStatus', 'Warning ⚠️', 'text-orange-600');
            } else {
                updateStatus('tempStatus', 'Normal ✓', 'text-green-600');
            }
            
            if (humidity > 70) {
                updateStatus('humidityStatus', 'High 💧', 'text-blue-600');
            } else if (humidity < 30) {
                updateStatus('humidityStatus', 'Low 💧', 'text-orange-600');
            } else {
                updateStatus('humidityStatus', 'Normal ✓', 'text-green-600');
            }
            
            let signalAlert = 0;
            if (signal < CONFIG.SIGNAL_CRITICAL) {
                signalAlert = 2;
                updateStatus('signalStatus', 'Critical 🔴', 'text-red-600');
            } else if (signal < CONFIG.SIGNAL_WARNING) {
                signalAlert = 1;
                updateStatus('signalStatus', 'Weak ⚠️', 'text-orange-600');
            } else {
                updateStatus('signalStatus', 'Excellent ✓', 'text-green-600');
            }
            
            let connectionAlert = 0;
            
            const now = new Date();
            alertHistory.timestamps.push(now.toLocaleTimeString());
            alertHistory.tempAlerts.push(tempAlert);
            alertHistory.signalAlerts.push(signalAlert);
            alertHistory.connectionAlerts.push(connectionAlert);
            
            if (alertHistory.timestamps.length > 50) {
                alertHistory.timestamps.shift();
                alertHistory.tempAlerts.shift();
                alertHistory.signalAlerts.shift();
                alertHistory.connectionAlerts.shift();
            }
            
            if (alertChart && currentAlertPeriod === 'hour') {
                alertChart.data.labels = alertHistory.timestamps;
                alertChart.data.datasets[0].data = alertHistory.tempAlerts;
                alertChart.data.datasets[1].data = alertHistory.signalAlerts;
                alertChart.data.datasets[2].data = alertHistory.connectionAlerts;
                alertChart.update('none');
            }
            
            let aiScore = 100;
            aiScore -= tempAlert * 20;
            aiScore -= signalAlert * 15;
            aiScore = Math.max(0, Math.min(100, aiScore));
            
            animateValue('aiScore', aiScore + '%');
            document.getElementById('aiScoreBar').style.width = aiScore + '%';
            
            if (aiScore >= 80) {
                updateStatus('aiHealth', 'Excellent ✓', 'text-green-600');
            } else if (aiScore >= 50) {
                updateStatus('aiHealth', 'Warning ⚠️', 'text-orange-600');
            } else {
                updateStatus('aiHealth', 'Critical 🔴', 'text-red-600');
            }
            
            if (tempAlert === 2 || signalAlert === 2) {
                updateStatus('anomalyText', 'Critical 🔴', 'text-red-600');
            } else if (tempAlert === 1 || signalAlert === 1) {
                updateStatus('anomalyText', 'Warning ⚠️', 'text-orange-600');
            } else {
                updateStatus('anomalyText', 'Normal ✓', 'text-green-600');
            }
            
        } else {
            // Mode DÉMO
            isRealMode = false;
            updateModeDisplay(false);
            updateDHT11Status(false);
            document.getElementById('deviceStatus').innerHTML = 'Demo Mode';
            document.getElementById('deviceStatus').className = 'text-2xl font-bold text-yellow-600';
            
            // Mettre à jour les données de démonstration
            updateDemoData();
            
            if (sensorChart && sensorChart.data.labels.length === 0) {
                generateDemoSensorData('day', 24);
            }
            if (alertChart && alertChart.data.labels.length === 0) {
                generateDemoAlertData('day', 24);
            }
        }
    } catch(error) {
        console.error('Error:', error);
        isRealMode = false;
        updateModeDisplay(false);
        updateDHT11Status(false);
        document.getElementById('deviceStatus').innerHTML = 'Demo Mode';
        document.getElementById('deviceStatus').className = 'text-2xl font-bold text-yellow-600';
        updateDemoData();
    }
}

function updateDemoData() {
    const now = new Date();
    const temp = 22 + Math.sin(Date.now() / 10000) * 3;
    const humidity = 55 + Math.cos(Date.now() / 8000) * 10;
    const signal = 75 + Math.sin(Date.now() / 15000) * 15;
    
    animateValue('currentTemp', temp.toFixed(1) + '°C');
    animateValue('currentHumidity', humidity.toFixed(1) + '%');
    animateValue('currentSignal', Math.min(100, Math.max(0, signal)).toFixed(0) + '%');
    
    let aiScore = 85 + Math.sin(Date.now() / 20000) * 10;
    animateValue('aiScore', aiScore.toFixed(0) + '%');
    document.getElementById('aiScoreBar').style.width = aiScore + '%';
    
    updateStatus('tempStatus', 'Demo Mode', 'text-yellow-600');
    updateStatus('humidityStatus', 'Demo Mode', 'text-yellow-600');
    updateStatus('signalStatus', 'Demo Mode', 'text-yellow-600');
    updateStatus('aiHealth', 'Demo Mode', 'text-yellow-600');
    updateStatus('anomalyText', 'Demo Mode', 'text-yellow-600');
}

function animateValue(elementId, newValue) {
    const element = document.getElementById(elementId);
    if (element && element.innerText !== newValue) {
        element.classList.add('changed');
        element.innerText = newValue;
        setTimeout(() => element.classList.remove('changed'), 300);
    }
}

function updateStatus(elementId, newText, className) {
    const element = document.getElementById(elementId);
    if (element && element.innerText !== newText) {
        element.innerText = newText;
        element.className = `mt-2 font-bold ${className}`;
    }
}

// ============================================
// PDF GENERATION WITH LOGO
// ============================================

async function generatePDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    
    doc.setFillColor(59, 130, 246);
    doc.roundedRect(14, 14, 30, 30, 5, 5, 'F');
    doc.setTextColor(255, 255, 255);
    doc.setFontSize(18);
    doc.setFont('helvetica', 'bold');
    doc.text('E', 22, 34);
    doc.setFontSize(8);
    doc.text('NET', 28, 34);
    
    doc.setFillColor(59, 130, 246);
    doc.rect(0, 0, 210, 50, 'F');
    
    doc.setTextColor(255, 255, 255);
    doc.setFontSize(24);
    doc.setFont('helvetica', 'bold');
    doc.text('ENVIRONET', 55, 28);
    doc.setFontSize(12);
    doc.setFont('helvetica', 'normal');
    doc.text('Environmental Monitoring Report', 55, 40);
    
    doc.setDrawColor(255, 255, 255);
    doc.setLineWidth(0.5);
    doc.line(55, 45, 190, 45);
    
    doc.setTextColor(100, 100, 100);
    doc.setFontSize(10);
    doc.text(`Generated: ${new Date().toLocaleString()}`, 20, 65);
    doc.text(`User: <?= $username ?>`, 20, 72);
    doc.text(`Report ID: ENV-${Date.now()}`, 20, 79);
    doc.text(`Mode: ${isRealMode ? 'REAL (Connected)' : 'DEMO (Simulated)'}`, 20, 86);
    
    const dhtStatus = document.getElementById('dhtStatusText').innerText;
    doc.text(`DHT11 Sensor: ${dhtStatus}`, 20, 93);
    
    doc.setDrawColor(59, 130, 246);
    doc.setLineWidth(0.5);
    doc.line(20, 103, 190, 103);
    
    doc.setTextColor(0, 0, 0);
    doc.setFontSize(16);
    doc.setFont('helvetica', 'bold');
    doc.text('Executive Summary', 20, 117);
    
    doc.setFontSize(10);
    doc.setFont('helvetica', 'normal');
    doc.text(`Temperature: ${document.getElementById('currentTemp').innerText}`, 25, 132);
    doc.text(`Humidity: ${document.getElementById('currentHumidity').innerText}`, 25, 142);
    doc.text(`Signal: ${document.getElementById('currentSignal').innerText}`, 25, 152);
    doc.text(`AI Score: ${document.getElementById('aiScore').innerText}`, 25, 162);
    doc.text(`System Status: ${document.getElementById('aiHealth').innerText}`, 25, 172);
    
    const alertCount = alertHistory.tempAlerts.filter(a => a > 0).length;
    doc.text(`Total Alerts: ${alertCount}`, 25, 182);
    
    const pageCount = doc.internal.getNumberOfPages();
    for(let i = 1; i <= pageCount; i++) {
        doc.setPage(i);
        doc.setFillColor(59, 130, 246);
        doc.roundedRect(20, doc.internal.pageSize.height - 15, 8, 8, 2, 2, 'F');
        doc.setTextColor(255, 255, 255);
        doc.setFontSize(5);
        doc.setFont('helvetica', 'bold');
        doc.text('E', 22, doc.internal.pageSize.height - 10);
        
        doc.setTextColor(150, 150, 150);
        doc.setFontSize(8);
        doc.setFont('helvetica', 'normal');
        doc.text(`ENVIRONET Analytics - Page ${i} of ${pageCount}`, 35, doc.internal.pageSize.height - 10);
        doc.text(`Generated by <?= $username ?>`, 140, doc.internal.pageSize.height - 10);
    }
    
    const filename = `ENVIRONET_Report_${new Date().toISOString().slice(0,19).replace(/:/g, '-')}.pdf`;
    doc.save(filename);
    
    showNotification('PDF Report generated!', 'success');
}

function showNotification(message, type) {
    const notification = document.createElement('div');
    notification.className = `fixed top-20 right-6 z-50 px-6 py-3 rounded-lg shadow-lg text-white ${
        type === 'success' ? 'bg-green-500' : 'bg-red-500'
    } transition-all duration-300 transform translate-x-full`;
    notification.innerHTML = `<div class="flex items-center gap-2">✓ ${message}</div>`;
    document.body.appendChild(notification);
    setTimeout(() => notification.style.transform = 'translateX(0)', 100);
    setTimeout(() => {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// ============================================
// INITIALIZATION
// ============================================

document.addEventListener('DOMContentLoaded', () => {
    initCharts();
    loadSensorData('day');
    loadAlertData('day');
    loadLatestData();
    setInterval(() => {
        loadLatestData();
        loadSensorData(currentSensorPeriod);
        loadAlertData(currentAlertPeriod);
    }, CONFIG.UPDATE_INTERVAL);
    console.log('✅ Real-time monitoring started');
});
</script>
</body>
</html>
