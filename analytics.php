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
<title>ENVIRONET Analytics - Real Time</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns"></script>
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
    transition: transform 0.2s, box-shadow 0.2s;
}
.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.12);
}
.chart-box{
    height:400px;
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
}
.filter-btn.active {
    background-color: #3b82f6;
    color: white;
}
.realtime-badge {
    animation: pulse 2s infinite;
}
@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.6; }
    100% { opacity: 1; }
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
            <a href="dashboard.php" 
               class="bg-blue-600 text-white px-5 py-2 rounded-lg btn-primary flex items-center gap-2">
                ← Dashboard
            </a>
            <button onclick="generatePDF()" 
                    class="bg-purple-700 text-white px-5 py-2 rounded-lg btn-primary flex items-center gap-2">
                📄 PDF Report
            </button>
        </div>
    </div>

    <!-- TOP CARDS avec animations temps réel -->
    <div class="grid md:grid-cols-4 gap-5 mb-6">
        <div class="card">
            <h2 class="font-bold mb-4">📡 Device Status</h2>
            <p class="text-gray-500 text-sm">State</p>
            <p id="deviceStatus" class="text-2xl font-bold text-green-600">Online</p>
            <p class="text-gray-500 text-sm mt-3">DHT11 Sensor</p>
            <p id="dhtStatus" class="font-bold text-green-600">Connected</p>
            <p class="text-gray-500 text-sm mt-3">Last Update</p>
            <p id="lastUpdate" class="font-bold">--</p>
        </div>

        <div class="card" id="tempCard">
            <h2 class="font-bold mb-4">🌡 Temperature</h2>
            <p id="currentTemp" class="text-4xl font-black text-orange-600">--°C</p>
            <p id="tempStatus" class="mt-2 font-bold text-green-600">Normal</p>
        </div>

        <div class="card">
            <h2 class="font-bold mb-4">💧 Humidity</h2>
            <p id="currentHumidity" class="text-4xl font-black text-blue-600">--%</p>
            <p class="mt-2 font-bold text-blue-500">DHT11 Sensor</p>
        </div>

        <div class="card">
            <h2 class="font-bold mb-4">📶 Network</h2>
            <p id="currentSignal" class="text-4xl font-black text-indigo-600">--%</p>
            <p id="signalStatus" class="mt-2 font-bold text-green-600">Excellent</p>
        </div>
    </div>

    <!-- AI SECTION avec mise à jour temps réel -->
    <div class="card mb-6">
        <h2 class="text-2xl font-bold mb-5">🧠 Artificial Intelligence - Real Time Analysis</h2>
        <div class="grid md:grid-cols-4 gap-4">
            <div class="bg-gray-50 rounded-xl p-4">
                <p class="text-gray-500 text-sm">AI Score</p>
                <p id="aiScore" class="text-3xl font-bold text-purple-700">--</p>
                <div class="w-full bg-gray-200 rounded-full mt-2 h-2">
                    <div id="aiScoreBar" class="bg-purple-600 rounded-full h-2 transition-all duration-500" style="width: 0%"></div>
                </div>
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
                <p class="text-xs text-gray-400 mt-1">Real time collection</p>
            </div>
        </div>
    </div>

    <!-- ALERT STATS en temps réel -->
    <div class="grid md:grid-cols-3 gap-5 mb-6">
        <div class="card">
            <h2 class="font-bold mb-2">⚠️ Temperature Alerts</h2>
            <p id="totalTempAlerts" class="text-3xl font-bold text-red-600">0</p>
            <p class="text-xs text-gray-500 mt-1">Real time detections</p>
        </div>
        <div class="card">
            <h2 class="font-bold mb-2">📶 Signal Alerts</h2>
            <p id="totalSignalAlerts" class="text-3xl font-bold text-orange-600">0</p>
            <p class="text-xs text-gray-500 mt-1">Network quality</p>
        </div>
        <div class="card">
            <h2 class="font-bold mb-2">🔌 Connection Alerts</h2>
            <p id="totalConnAlerts" class="text-3xl font-bold text-purple-600">0</p>
            <p class="text-xs text-gray-500 mt-1">Device connectivity</p>
        </div>
    </div>

    <!-- CHARTS avec analyse temps réel -->
    <div class="grid lg:grid-cols-2 gap-6">
        <div class="card">
            <div class="flex justify-between items-center mb-4">
                <h2 class="font-bold">📈 Sensor History - Real Time</h2>
                <div class="flex gap-2">
                    <button onclick="setTimeFilter('24h')" id="filter24h" class="filter-btn px-3 py-1 text-sm rounded-lg bg-gray-200 hover:bg-blue-500 hover:text-white transition">24 Hours</button>
                    <button onclick="setTimeFilter('7d')" id="filter7d" class="filter-btn px-3 py-1 text-sm rounded-lg bg-gray-200 hover:bg-blue-500 hover:text-white transition">7 Days</button>
                    <button onclick="setTimeFilter('30d')" id="filter30d" class="filter-btn px-3 py-1 text-sm rounded-lg bg-gray-200 hover:bg-blue-500 hover:text-white transition">30 Days</button>
                </div>
            </div>
            <div class="chart-box">
                <canvas id="historyChart"></canvas>
            </div>
            <p class="text-xs text-gray-400 text-center mt-2">↻ Updates in real time every 3 seconds</p>
        </div>
        <div class="card">
            <h2 class="font-bold mb-4">⚠️ Alert History - Real Time</h2>
            <div class="chart-box">
                <canvas id="alertChart"></canvas>
            </div>
            <p class="text-xs text-gray-400 text-center mt-2">↻ Instant alert detection</p>
        </div>
    </div>
</div>

<script>
// ============================================
// REAL TIME ANALYTICS SYSTEM - WebSocket Ready
// ============================================

const CONFIG = {
    TEMP_WARNING: 28,
    TEMP_CRITICAL: 35,
    SIGNAL_WARNING: 60,
    SIGNAL_CRITICAL: 30,
    SENSOR_TIMEOUT: 30000,
    MAX_HISTORY: 1000, // Store up to 1000 points
    UPDATE_INTERVAL: 3000 // Update every 3 seconds for real-time feel
};

let allDataPoints = [];
let currentFilter = '24h';
let historyChart;
let alertChart;
let lastUpdateTime = 0;
let updateCount = 0;

// ============================================
// INIT CHARTS WITH TIME AXIS
// ============================================

function initCharts() {
    // HISTORY CHART
    const hctx = document.getElementById('historyChart').getContext('2d');
    historyChart = new Chart(hctx, {
        type: 'line',
        data: {
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
            animation: {
                duration: 500 // Smooth animations for real-time updates
            },
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        boxWidth: 10
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            let value = context.raw;
                            if (context.dataset.label === 'Temperature (°C)') {
                                return label + ': ' + value.y.toFixed(1) + '°C';
                            }
                            if (context.dataset.label === 'Humidity (%)') {
                                return label + ': ' + value.y.toFixed(1) + '%';
                            }
                            if (context.dataset.label === 'Signal Strength (%)') {
                                return label + ': ' + value.y.toFixed(0) + '%';
                            }
                            return label + ': ' + value.y;
                        },
                        title: function(context) {
                            let date = new Date(context[0].raw.x);
                            return date.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                x: {
                    type: 'time',
                    time: {
                        unit: 'hour',
                        displayFormats: {
                            hour: 'HH:mm:ss',
                            day: 'MMM dd HH:mm',
                            week: 'MMM dd',
                            month: 'MMM yyyy'
                        },
                        tooltipFormat: 'PPpp'
                    },
                    title: {
                        display: true,
                        text: 'Timestamp (Real Time)'
                    }
                },
                y: {
                    title: {
                        display: true,
                        text: 'Temperature (°C) / Humidity (%)'
                    },
                    min: 0,
                    max: 100,
                    grid: {
                        color: 'rgba(0,0,0,0.05)'
                    }
                },
                y1: {
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Signal Strength (%)'
                    },
                    min: 0,
                    max: 100,
                    grid: {
                        drawOnChartArea: false
                    }
                }
            }
        }
    });

    // ALERT CHART
    const actx = document.getElementById('alertChart').getContext('2d');
    alertChart = new Chart(actx, {
        type: 'line',
        data: {
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
                    pointHoverRadius: 7
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
                    pointHoverRadius: 7
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
                    pointHoverRadius: 7
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 500
            },
            scales: {
                x: {
                    type: 'time',
                    time: {
                        unit: 'hour',
                        displayFormats: {
                            hour: 'HH:mm:ss',
                            day: 'MMM dd HH:mm',
                            week: 'MMM dd',
                            month: 'MMM yyyy'
                        }
                    },
                    title: {
                        display: true,
                        text: 'Timestamp'
                    }
                },
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
                            const value = context.raw.y;
                            if (value === 0) return context.dataset.label + ': Normal';
                            if (value === 1) return context.dataset.label + ': Warning';
                            if (value === 2) return context.dataset.label + ': Critical';
                            return context.dataset.label + ': ' + value;
                        },
                        title: function(context) {
                            let date = new Date(context[0].raw.x);
                            return date.toLocaleString();
                        }
                    }
                }
            }
        }
    });
}

// ============================================
// REAL TIME DATA FETCHING
// ============================================

async function fetchRealTimeData() {
    try {
        const response = await fetch(`get_latest_data.php?user_id=<?= $user_id ?>&_=${Date.now()}`);
        const json = await response.json();
        
        if (!json.success || !json.data) {
            setOfflineUI();
            return;
        }
        
        const data = json.data;
        const updateTime = new Date(data.created_at);
        
        // Update last update time
        if (!isNaN(updateTime.getTime())) {
            document.getElementById('lastUpdate').innerHTML = updateTime.toLocaleTimeString() + ':' + updateTime.getMilliseconds();
        }
        
        const temp = parseFloat(data.temperature);
        const humidity = parseFloat(data.humidity);
        const signal = parseFloat(data.signal_strength);
        
        // Update current values with animation
        animateValue('currentTemp', temp.toFixed(1) + '°C');
        animateValue('currentHumidity', humidity.toFixed(1) + '%');
        animateValue('currentSignal', signal.toFixed(0) + '%');
        
        // Real-time alert detection
        let tempAlert = 0;
        let signalAlert = 0;
        
        if (temp >= CONFIG.TEMP_CRITICAL) {
            tempAlert = 2;
            updateStatusWithAnimation('tempStatus', 'Critical 🔴', 'text-red-600');
            document.getElementById('tempCard').style.border = '2px solid #ef4444';
        } else if (temp >= CONFIG.TEMP_WARNING) {
            tempAlert = 1;
            updateStatusWithAnimation('tempStatus', 'Warning ⚠️', 'text-orange-600');
            document.getElementById('tempCard').style.border = '2px solid #f59e0b';
        } else {
            updateStatusWithAnimation('tempStatus', 'Normal ✓', 'text-green-600');
            document.getElementById('tempCard').style.border = 'none';
        }
        
        if (signal < CONFIG.SIGNAL_CRITICAL) {
            signalAlert = 2;
            updateStatusWithAnimation('signalStatus', 'Critical 🔴', 'text-red-600');
        } else if (signal < CONFIG.SIGNAL_WARNING) {
            signalAlert = 1;
            updateStatusWithAnimation('signalStatus', 'Weak ⚠️', 'text-orange-600');
        } else {
            updateStatusWithAnimation('signalStatus', 'Excellent ✓', 'text-green-600');
        }
        
        // Real-time AI Score
        let aiScore = 100;
        aiScore -= tempAlert * 20;
        aiScore -= signalAlert * 15;
        aiScore = Math.max(0, Math.min(100, aiScore));
        
        animateValue('aiScore', aiScore + '%');
        document.getElementById('aiScoreBar').style.width = aiScore + '%';
        
        if (aiScore >= 80) {
            updateStatusWithAnimation('aiHealth', 'Excellent ✓', 'text-green-600');
        } else if (aiScore >= 50) {
            updateStatusWithAnimation('aiHealth', 'Warning ⚠️', 'text-orange-600');
        } else {
            updateStatusWithAnimation('aiHealth', 'Critical 🔴', 'text-red-600');
        }
        
        if (tempAlert === 2 || signalAlert === 2) {
            updateStatusWithAnimation('anomalyText', 'Critical 🔴', 'text-red-600');
        } else if (tempAlert === 1 || signalAlert === 1) {
            updateStatusWithAnimation('anomalyText', 'Warning ⚠️', 'text-orange-600');
        } else {
            updateStatusWithAnimation('anomalyText', 'Normal ✓', 'text-green-600');
        }
        
        // Store data point
        const dataPoint = {
            timestamp: updateTime,
            temp: temp,
            humidity: humidity,
            signal: signal,
            tempAlert: tempAlert,
            signalAlert: signalAlert,
            connAlert: 0
        };
        
        allDataPoints.push(dataPoint);
        
        // Limit data points
        const thirtyDaysAgo = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000);
        allDataPoints = allDataPoints.filter(point => new Date(point.timestamp) >= thirtyDaysAgo);
        
        // Update alert counts in real-time
        const tempAlertSum = allDataPoints.filter(p => p.tempAlert > 0).length;
        const signalAlertSum = allDataPoints.filter(p => p.signalAlert > 0).length;
        
        animateValue('totalTempAlerts', tempAlertSum.toString());
        animateValue('totalSignalAlerts', signalAlertSum.toString());
        
        // Apply filter to update charts
        applyFilter();
        
        updateCount++;
        if (updateCount % 10 === 0) {
            console.log(`Real-time updates: ${updateCount} data points collected`);
        }
        
    } catch(err) {
        console.error('Real-time fetch error:', err);
        setOfflineUI();
    }
}

function animateValue(elementId, newValue) {
    const element = document.getElementById(elementId);
    if (element && element.innerText !== newValue) {
        element.style.transform = 'scale(1.1)';
        element.innerText = newValue;
        setTimeout(() => {
            element.style.transform = 'scale(1)';
        }, 200);
    }
}

function updateStatusWithAnimation(elementId, newText, className) {
    const element = document.getElementById(elementId);
    if (element && element.innerText !== newText) {
        element.style.opacity = '0.5';
        element.innerText = newText;
        element.className = `mt-2 font-bold ${className}`;
        setTimeout(() => {
            element.style.opacity = '1';
        }, 150);
    }
}

function setOfflineUI() {
    document.getElementById('deviceStatus').innerHTML = 'Offline';
    document.getElementById('deviceStatus').className = 'text-2xl font-bold text-red-600';
    document.getElementById('dhtStatus').innerHTML = 'Disconnected';
    document.getElementById('dhtStatus').className = 'font-bold text-red-600';
}

// ============================================
// FILTER DATA BY TIME RANGE
// ============================================

function setTimeFilter(filter) {
    currentFilter = filter;
    
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.classList.remove('active', 'bg-blue-600', 'text-white');
        btn.classList.add('bg-gray-200');
    });
    
    let activeBtn;
    if (filter === '24h') activeBtn = document.getElementById('filter24h');
    else if (filter === '7d') activeBtn = document.getElementById('filter7d');
    else activeBtn = document.getElementById('filter30d');
    
    activeBtn.classList.remove('bg-gray-200');
    activeBtn.classList.add('active', 'bg-blue-600', 'text-white');
    
    applyFilter();
}

function applyFilter() {
    const now = new Date();
    let filterTime;
    
    switch(currentFilter) {
        case '24h':
            filterTime = new Date(now.getTime() - 24 * 60 * 60 * 1000);
            break;
        case '7d':
            filterTime = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);
            break;
        case '30d':
            filterTime = new Date(now.getTime() - 30 * 24 * 60 * 60 * 1000);
            break;
        default:
            filterTime = new Date(now.getTime() - 24 * 60 * 60 * 1000);
    }
    
    const filteredData = allDataPoints.filter(point => new Date(point.timestamp) >= filterTime);
    
    const filteredTemp = [];
    const filteredHumidity = [];
    const filteredSignal = [];
    const filteredTempAlerts = [];
    const filteredSignalAlerts = [];
    
    filteredData.forEach(point => {
        filteredTemp.push({x: point.timestamp, y: point.temp});
        filteredHumidity.push({x: point.timestamp, y: point.humidity});
        filteredSignal.push({x: point.timestamp, y: point.signal});
        filteredTempAlerts.push({x: point.timestamp, y: point.tempAlert});
        filteredSignalAlerts.push({x: point.timestamp, y: point.signalAlert});
    });
    
    if (historyChart) {
        historyChart.data.datasets[0].data = filteredTemp;
        historyChart.data.datasets[1].data = filteredHumidity;
        historyChart.data.datasets[2].data = filteredSignal;
        
        if (currentFilter === '24h') {
            historyChart.options.scales.x.time.unit = 'hour';
            historyChart.options.scales.x.time.displayFormats.hour = 'HH:mm:ss';
        } else if (currentFilter === '7d') {
            historyChart.options.scales.x.time.unit = 'day';
            historyChart.options.scales.x.time.displayFormats.day = 'MMM dd HH:mm';
        } else {
            historyChart.options.scales.x.time.unit = 'day';
            historyChart.options.scales.x.time.displayFormats.day = 'MMM dd';
        }
        
        historyChart.update('active');
    }
    
    if (alertChart) {
        alertChart.data.datasets[0].data = filteredTempAlerts;
        alertChart.data.datasets[1].data = filteredSignalAlerts;
        
        if (currentFilter === '24h') {
            alertChart.options.scales.x.time.unit = 'hour';
        } else if (currentFilter === '7d') {
            alertChart.options.scales.x.time.unit = 'day';
        } else {
            alertChart.options.scales.x.time.unit = 'day';
        }
        
        alertChart.update('active');
    }
    
    document.getElementById('dataCount').innerHTML = filteredData.length;
}

// ============================================
// PDF GENERATION
// ============================================

async function generatePDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    
    doc.setFillColor(59, 130, 246);
    doc.rect(0, 0, 210, 40, 'F');
    
    doc.setTextColor(255, 255, 255);
    doc.setFontSize(24);
    doc.setFont('helvetica', 'bold');
    doc.text('ENVIRONET', 20, 25);
    doc.setFontSize(14);
    doc.text('Real Time Environmental Monitoring System', 20, 35);
    
    doc.setTextColor(100, 100, 100);
    doc.setFontSize(10);
    doc.text(`Report Generated: ${new Date().toLocaleString()}`, 20, 55);
    doc.text(`User: ${'<?= $username ?>'}`, 20, 62);
    doc.text(`Real-time Data Points: ${allDataPoints.length}`, 20, 69);
    
    const tempValue = document.getElementById('currentTemp').innerText;
    const aiScore = document.getElementById('aiScore').innerText;
    
    doc.setFontSize(16);
    doc.setTextColor(0, 0, 0);
    doc.text('Real-Time Executive Summary', 20, 90);
    
    doc.setFontSize(10);
    const summary = [
        `• Current Temperature: ${tempValue} (Real-time reading)`,
        `• AI Health Score: ${aiScore}`,
        `• System Status: Active - Real-time monitoring enabled`,
        `• Update Frequency: Every ${CONFIG.UPDATE_INTERVAL/1000} seconds`,
        `• Total Data Points Collected: ${allDataPoints.length}`
    ];
    
    let yPos = 105;
    summary.forEach(line => {
        doc.text(line, 25, yPos);
        yPos += 7;
    });
    
    const filename = `ENVIRONET_RealTime_Report_${new Date().toISOString().slice(0,19).replace(/:/g, '-')}.pdf`;
    doc.save(filename);
    
    showNotification('Real-time PDF Report generated!', 'success');
}

function showNotification(message, type) {
    const notification = document.createElement('div');
    notification.className = `fixed top-20 right-6 z-50 px-6 py-3 rounded-lg shadow-lg text-white ${
        type === 'success' ? 'bg-green-500' : 'bg-red-500'
    } transition-all duration-300`;
    notification.innerHTML = `<div class="flex items-center gap-2"><span>✓</span><span>${message}</span></div>`;
    document.body.appendChild(notification);
    setTimeout(() => notification.remove(), 3000);
}

// ============================================
// START REAL TIME MONITORING
// ============================================

document.addEventListener('DOMContentLoaded', () => {
    initCharts();
    setTimeFilter('24h');
    
    // Initial load
    fetchRealTimeData();
    
    // Real-time updates every 3 seconds
    setInterval(() => {
        fetchRealTimeData();
    }, CONFIG.UPDATE_INTERVAL);
    
    // Display real-time status
    console.log('🚀 Real-time monitoring started - Updates every 3 seconds');
});
</script>
</body>
</html>
