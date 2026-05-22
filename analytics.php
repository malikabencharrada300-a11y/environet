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
<title>ENVIRONET Analytics</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
<style>
body{
    background:#f4f7fc;
    font-family:Arial;
}
.card{
    background:white;
    border-radius:20px;
    padding:20px;
    box-shadow:0 4px 15px rgba(0,0,0,0.08);
}
.chart-box{
    height:300px;
}
</style>
</head>
<body class="p-6">
<div class="max-w-7xl mx-auto">
    <!-- HEADER -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-blue-700">ENVIRONET Analytics</h1>
            <p class="text-gray-500">Real Time IoT Monitoring</p>
        </div>
        <button onclick="generatePDF()" class="bg-purple-700 text-white px-5 py-2 rounded-lg hover:bg-purple-800 transition">
            PDF Report
        </button>
    </div>

    <!-- TOP CARDS -->
    <div class="grid md:grid-cols-4 gap-5 mb-6">
        <!-- DEVICE -->
        <div class="card">
            <h2 class="font-bold mb-4">📡 Device Status</h2>
            <p class="text-gray-500 text-sm">State</p>
            <p id="deviceStatus" class="text-2xl font-bold text-green-600">Online</p>
            <p class="text-gray-500 text-sm mt-3">DHT11 Sensor</p>
            <p id="dhtStatus" class="font-bold text-green-600">Connected</p>
            <p class="text-gray-500 text-sm mt-3">Last Update</p>
            <p id="lastUpdate" class="font-bold">--</p>
        </div>

        <!-- TEMPERATURE -->
        <div class="card">
            <h2 class="font-bold mb-4">🌡 Temperature</h2>
            <p id="currentTemp" class="text-4xl font-black text-orange-600">--°C</p>
            <p id="tempStatus" class="mt-2 font-bold text-green-600">Normal</p>
        </div>

        <!-- HUMIDITY -->
        <div class="card">
            <h2 class="font-bold mb-4">💧 Humidity</h2>
            <p id="currentHumidity" class="text-4xl font-black text-blue-600">--%</p>
            <p class="mt-2 font-bold text-blue-500">DHT11 Sensor</p>
        </div>

        <!-- SIGNAL -->
        <div class="card">
            <h2 class="font-bold mb-4">📶 Network</h2>
            <p id="currentSignal" class="text-4xl font-black text-indigo-600">--%</p>
            <p id="signalStatus" class="mt-2 font-bold text-green-600">Excellent</p>
        </div>
    </div>

    <!-- AI SECTION -->
    <div class="card mb-6">
        <h2 class="text-2xl font-bold mb-5">🧠 Artificial Intelligence</h2>
        <div class="grid md:grid-cols-4 gap-4">
            <div class="bg-gray-50 rounded-xl p-4">
                <p class="text-gray-500 text-sm">AI Score</p>
                <p id="aiScore" class="text-3xl font-bold text-purple-700">--</p>
            </div>
            <div class="bg-gray-50 rounded-xl p-4">
                <p class="text-gray-500 text-sm">Health</p>
                <p id="aiHealth" class="text-3xl font-bold text-green-600">--</p>
            </div>
            <div class="bg-gray-50 rounded-xl p-4">
                <p class="text-gray-500 text-sm">Anomaly</p>
                <p id="anomalyText" class="text-3xl font-bold text-orange-600">--</p>
            </div>
            <div class="bg-gray-50 rounded-xl p-4">
                <p class="text-gray-500 text-sm">Data Points</p>
                <p id="dataCount" class="text-3xl font-bold text-blue-600">0</p>
            </div>
        </div>
    </div>

    <!-- ALERT STATS -->
    <div class="grid md:grid-cols-3 gap-5 mb-6">
        <div class="card">
            <h2 class="font-bold mb-2">⚠️ Temperature Alerts</h2>
            <p id="totalTempAlerts" class="text-3xl font-bold text-red-600">0</p>
        </div>
        <div class="card">
            <h2 class="font-bold mb-2">📶 Signal Alerts</h2>
            <p id="totalSignalAlerts" class="text-3xl font-bold text-orange-600">0</p>
        </div>
        <div class="card">
            <h2 class="font-bold mb-2">🔌 Connection Alerts</h2>
            <p id="totalConnAlerts" class="text-3xl font-bold text-purple-600">0</p>
        </div>
    </div>

    <!-- CHARTS -->
    <div class="grid lg:grid-cols-2 gap-6">
        <div class="card">
            <h2 class="font-bold mb-4">📈 Sensor History</h2>
            <div class="chart-box">
                <canvas id="historyChart"></canvas>
            </div>
        </div>
        <div class="card">
            <h2 class="font-bold mb-4">⚠️ Alert History</h2>
            <div class="chart-box">
                <canvas id="alertChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
// ============================================
// REAL TIME DHT11 + ALERT ANALYTICS SYSTEM
// ============================================

const CONFIG = {
    TEMP_WARNING: 28,    // Increased from 24°C
    TEMP_CRITICAL: 35,   // Increased from 28°C
    TEMP_MIN: 10,        // Minimum normal temperature
    SIGNAL_WARNING: 60,
    SIGNAL_CRITICAL: 30,
    SENSOR_TIMEOUT: 30000,
    MAX_HISTORY: 50
};

let tempHistory = [];
let signalHistory = [];
let humidityHistory = [];
let labelsHistory = [];
let tempAlertHistory = [];
let signalAlertHistory = [];
let connectionAlertHistory = [];
let historyChart;
let alertChart;

// ============================================
// INIT CHARTS
// ============================================

function initCharts() {
    // HISTORY CHART
    const hctx = document.getElementById('historyChart').getContext('2d');
    historyChart = new Chart(hctx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [
                {
                    label: 'Temperature °C',
                    data: [],
                    borderColor: '#f97316',
                    backgroundColor: 'rgba(249,115,22,0.1)',
                    fill: true,
                    tension: 0.4,
                    yAxisID: 'y'
                },
                {
                    label: 'Humidity %',
                    data: [],
                    borderColor: '#06b6d4',
                    backgroundColor: 'rgba(6,182,212,0.1)',
                    fill: true,
                    tension: 0.4,
                    yAxisID: 'y'
                },
                {
                    label: 'Signal %',
                    data: [],
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37,99,235,0.1)',
                    fill: true,
                    tension: 0.4,
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
            plugins: {
                legend: {
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            let value = context.raw;
                            if (context.dataset.label === 'Temperature °C') {
                                return label + ': ' + value.toFixed(1) + '°C';
                            }
                            if (context.dataset.label === 'Humidity %') {
                                return label + ': ' + value.toFixed(1) + '%';
                            }
                            return label + ': ' + value.toFixed(0) + '%';
                        }
                    }
                }
            },
            scales: {
                y: {
                    title: {
                        display: true,
                        text: 'Temperature (°C) / Humidity (%)'
                    },
                    min: 0,
                    max: 100
                },
                y1: {
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Signal Strength (%)'
                    },
                    min: 0,
                    max: 100
                }
            }
        }
    });

    // ALERT CHART
    const actx = document.getElementById('alertChart').getContext('2d');
    alertChart = new Chart(actx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [
                {
                    label: 'Temperature Alerts',
                    data: [],
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239,68,68,0.1)',
                    fill: true,
                    tension: 0.4,
                    stepped: true
                },
                {
                    label: 'Signal Alerts',
                    data: [],
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245,158,11,0.1)',
                    fill: true,
                    tension: 0.4,
                    stepped: true
                },
                {
                    label: 'Connection Alerts',
                    data: [],
                    borderColor: '#8b5cf6',
                    backgroundColor: 'rgba(139,92,246,0.1)',
                    fill: true,
                    tension: 0.4,
                    stepped: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    title: {
                        display: true,
                        text: 'Alert Level'
                    },
                    min: 0,
                    max: 2.5,
                    ticks: {
                        stepSize: 0.5,
                        callback: function(value) {
                            if (value === 0) return '✓ Normal';
                            if (value === 1) return '⚠ Warning';
                            if (value === 2) return '🔴 Critical';
                            return '';
                        }
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const value = context.raw;
                            if (value === 0) return context.dataset.label + ': Normal';
                            if (value === 1) return context.dataset.label + ': Warning';
                            if (value === 2) return context.dataset.label + ': Critical';
                            return context.dataset.label + ': ' + value;
                        }
                    }
                }
            }
        }
    });
}

// ============================================
// OFFLINE UI
// ============================================

function setOfflineUI() {
    document.getElementById('deviceStatus').innerHTML = 'Offline';
    document.getElementById('deviceStatus').className = 'text-2xl font-bold text-red-600';
    document.getElementById('dhtStatus').innerHTML = 'Disconnected';
    document.getElementById('dhtStatus').className = 'font-bold text-red-600';
    document.getElementById('currentTemp').innerHTML = '--°C';
    document.getElementById('currentHumidity').innerHTML = '--%';
    document.getElementById('currentSignal').innerHTML = '--%';
    document.getElementById('anomalyText').innerHTML = 'Offline';
    document.getElementById('aiHealth').innerHTML = 'Offline';
    document.getElementById('aiScore').innerHTML = '--';
    document.getElementById('lastUpdate').innerHTML = '--:--:--';
}

// ============================================
// VALIDATE DATA
// ============================================

function isValidData(temp, humidity, signal) {
    // Check for realistic values
    if (isNaN(temp) || isNaN(humidity) || isNaN(signal)) return false;
    if (temp < -20 || temp > 60) return false; // Realistic temperature range
    if (humidity < 0 || humidity > 100) return false;
    if (signal < 0 || signal > 100) return false;
    return true;
}

// ============================================
// LOAD REAL TIME DATA
// ============================================

async function loadAnalytics() {
    try {
        const response = await fetch(`get_latest_data.php?user_id=<?= $user_id ?>`);
        const json = await response.json();
        
        if (!json.success || !json.data) {
            setOfflineUI();
            return;
        }
        
        const data = json.data;
        const now = Date.now();
        const createdAt = new Date(data.created_at).getTime();
        const diff = now - createdAt;
        
        // Fix: Properly format the last update time
        const updateTime = new Date(data.created_at);
        if (!isNaN(updateTime.getTime())) {
            document.getElementById('lastUpdate').innerHTML = updateTime.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
        } else {
            document.getElementById('lastUpdate').innerHTML = new Date().toLocaleTimeString();
        }
        
        // SENSOR OFFLINE CHECK
        if (diff > CONFIG.SENSOR_TIMEOUT) {
            setOfflineUI();
            connectionAlertHistory.push(1);
            updateAlertChart();
            return;
        }
        
        // SENSOR ONLINE
        document.getElementById('deviceStatus').innerHTML = 'Online';
        document.getElementById('deviceStatus').className = 'text-2xl font-bold text-green-600';
        document.getElementById('dhtStatus').innerHTML = 'Connected';
        document.getElementById('dhtStatus').className = 'font-bold text-green-600';
        
        // VALUES
        const temp = parseFloat(data.temperature);
        const humidity = parseFloat(data.humidity);
        const signal = parseFloat(data.signal_strength);
        
        // Validate data
        if (!isValidData(temp, humidity, signal)) {
            console.warn('Invalid data received:', {temp, humidity, signal});
            return;
        }
        
        document.getElementById('currentTemp').innerHTML = temp.toFixed(1) + '°C';
        document.getElementById('currentHumidity').innerHTML = humidity.toFixed(1) + '%';
        document.getElementById('currentSignal').innerHTML = signal.toFixed(0) + '%';
        
        // ALERT DETECTION
        let tempAlert = 0;
        let signalAlert = 0;
        let connectionAlert = 0;
        
        // TEMP ALERT - Adjusted thresholds
        if (temp >= CONFIG.TEMP_CRITICAL) {
            tempAlert = 2;
            document.getElementById('tempStatus').innerHTML = 'Critical 🔴';
            document.getElementById('tempStatus').className = 'mt-2 font-bold text-red-600';
        } else if (temp >= CONFIG.TEMP_WARNING) {
            tempAlert = 1;
            document.getElementById('tempStatus').innerHTML = 'Warning ⚠️';
            document.getElementById('tempStatus').className = 'mt-2 font-bold text-orange-600';
        } else {
            document.getElementById('tempStatus').innerHTML = 'Normal ✓';
            document.getElementById('tempStatus').className = 'mt-2 font-bold text-green-600';
        }
        
        // SIGNAL ALERT
        if (signal < CONFIG.SIGNAL_CRITICAL) {
            signalAlert = 2;
            document.getElementById('signalStatus').innerHTML = 'Critical 🔴';
            document.getElementById('signalStatus').className = 'mt-2 font-bold text-red-600';
        } else if (signal < CONFIG.SIGNAL_WARNING) {
            signalAlert = 1;
            document.getElementById('signalStatus').innerHTML = 'Weak ⚠️';
            document.getElementById('signalStatus').className = 'mt-2 font-bold text-orange-600';
        } else {
            document.getElementById('signalStatus').innerHTML = 'Excellent ✓';
            document.getElementById('signalStatus').className = 'mt-2 font-bold text-green-600';
        }
        
        // AI SCORE
        let aiScore = 100;
        aiScore -= tempAlert * 20;  // Reduced penalty
        aiScore -= signalAlert * 15;
        aiScore = Math.max(0, Math.min(100, aiScore));
        
        document.getElementById('aiScore').innerHTML = aiScore + '%';
        document.getElementById('dataCount').innerHTML = tempHistory.length;
        
        if (aiScore >= 80) {
            document.getElementById('aiHealth').innerHTML = 'Excellent ✓';
            document.getElementById('aiHealth').className = 'text-3xl font-bold text-green-600';
        } else if (aiScore >= 50) {
            document.getElementById('aiHealth').innerHTML = 'Warning ⚠️';
            document.getElementById('aiHealth').className = 'text-3xl font-bold text-orange-600';
        } else {
            document.getElementById('aiHealth').innerHTML = 'Critical 🔴';
            document.getElementById('aiHealth').className = 'text-3xl font-bold text-red-600';
        }
        
        // ANOMALY
        if (tempAlert === 2 || signalAlert === 2) {
            document.getElementById('anomalyText').innerHTML = 'Critical 🔴';
            document.getElementById('anomalyText').className = 'text-3xl font-bold text-red-600';
        } else if (tempAlert === 1 || signalAlert === 1) {
            document.getElementById('anomalyText').innerHTML = 'Warning ⚠️';
            document.getElementById('anomalyText').className = 'text-3xl font-bold text-orange-600';
        } else {
            document.getElementById('anomalyText').innerHTML = 'Normal ✓';
            document.getElementById('anomalyText').className = 'text-3xl font-bold text-green-600';
        }
        
        // HISTORY - Only add if data changed significantly (optional)
        const lastTemp = tempHistory[tempHistory.length - 1];
        const lastSignal = signalHistory[signalHistory.length - 1];
        
        // Add to history (you can add throttling here if needed)
        tempHistory.push(temp);
        humidityHistory.push(humidity);
        signalHistory.push(signal);
        labelsHistory.push(updateTime.toLocaleTimeString());
        tempAlertHistory.push(tempAlert);
        signalAlertHistory.push(signalAlert);
        connectionAlertHistory.push(connectionAlert);
        
        // LIMIT
        const maxHistory = CONFIG.MAX_HISTORY;
        while (tempHistory.length > maxHistory) {
            tempHistory.shift();
            humidityHistory.shift();
            signalHistory.shift();
            labelsHistory.shift();
            tempAlertHistory.shift();
            signalAlertHistory.shift();
            connectionAlertHistory.shift();
        }
        
        updateCharts();
        
        // COUNTERS - Count only warnings and criticals
        const tempAlertSum = tempAlertHistory.filter(v => v > 0).length;
        const signalAlertSum = signalAlertHistory.filter(v => v > 0).length;
        const connAlertSum = connectionAlertHistory.filter(v => v > 0).length;
        
        document.getElementById('totalTempAlerts').innerHTML = tempAlertSum;
        document.getElementById('totalSignalAlerts').innerHTML = signalAlertSum;
        document.getElementById('totalConnAlerts').innerHTML = connAlertSum;
        
    } catch(err) {
        console.error('Error loading analytics:', err);
        setOfflineUI();
    }
}

function updateCharts() {
    if (historyChart && historyChart.data) {
        historyChart.data.labels = [...labelsHistory];
        historyChart.data.datasets[0].data = [...tempHistory];
        historyChart.data.datasets[1].data = [...humidityHistory];
        historyChart.data.datasets[2].data = [...signalHistory];
        historyChart.update('none'); // Use 'none' for better performance
    }
    
    if (alertChart && alertChart.data) {
        alertChart.data.labels = [...labelsHistory];
        alertChart.data.datasets[0].data = [...tempAlertHistory];
        alertChart.data.datasets[1].data = [...signalAlertHistory];
        alertChart.data.datasets[2].data = [...connectionAlertHistory];
        alertChart.update('none');
    }
}

function updateAlertChart() {
    if (alertChart && alertChart.data) {
        alertChart.data.labels = [...labelsHistory];
        alertChart.data.datasets[0].data = [...tempAlertHistory];
        alertChart.data.datasets[1].data = [...signalAlertHistory];
        alertChart.data.datasets[2].data = [...connectionAlertHistory];
        alertChart.update('none');
    }
}

// ============================================
// PDF GENERATION
// ============================================

async function generatePDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    
    // Add title
    doc.setFontSize(20);
    doc.text('ENVIRONET Analytics Report', 20, 20);
    
    doc.setFontSize(12);
    doc.text(`Generated: ${new Date().toLocaleString()}`, 20, 35);
    
    // Add current readings
    doc.setFontSize(14);
    doc.text('Current Readings:', 20, 55);
    doc.setFontSize(12);
    doc.text(`Temperature: ${document.getElementById('currentTemp').innerText}`, 30, 70);
    doc.text(`Humidity: ${document.getElementById('currentHumidity').innerText}`, 30, 80);
    doc.text(`Signal Strength: ${document.getElementById('currentSignal').innerText}`, 30, 90);
    doc.text(`AI Score: ${document.getElementById('aiScore').innerText}`, 30, 100);
    doc.text(`System Health: ${document.getElementById('aiHealth').innerText}`, 30, 110);
    
    // Add alert summary
    doc.text('Alert Summary:', 20, 130);
    doc.text(`Temperature Alerts: ${document.getElementById('totalTempAlerts').innerText}`, 30, 145);
    doc.text(`Signal Alerts: ${document.getElementById('totalSignalAlerts').innerText}`, 30, 155);
    doc.text(`Connection Alerts: ${document.getElementById('totalConnAlerts').innerText}`, 30, 165);
    
    // Save PDF
    doc.save('environet-report.pdf');
}

// ============================================
// START REAL TIME
// ============================================

document.addEventListener('DOMContentLoaded', () => {
    initCharts();
    loadAnalytics();
    // Update every 5 seconds
    setInterval(() => {
        loadAnalytics();
    }, 5000);
});
</script>
</body>
</html>
