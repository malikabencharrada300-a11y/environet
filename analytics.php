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
</style>
</head>
<body class="p-6">
<div class="max-w-7xl mx-auto">
    <!-- HEADER -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-blue-700">🌍 ENVIRONET Analytics</h1>
            <p class="text-gray-500">Real Time IoT Monitoring & Intelligence</p>
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
            <div class="flex justify-between items-center mb-4">
                <h2 class="font-bold">📈 Sensor History</h2>
                <div class="flex gap-2">
                    <button onclick="setTimeFilter('24h')" id="filter24h" class="filter-btn px-3 py-1 text-sm rounded-lg bg-gray-200 hover:bg-blue-500 hover:text-white transition">24 Hours</button>
                    <button onclick="setTimeFilter('7d')" id="filter7d" class="filter-btn px-3 py-1 text-sm rounded-lg bg-gray-200 hover:bg-blue-500 hover:text-white transition">7 Days</button>
                    <button onclick="setTimeFilter('30d')" id="filter30d" class="filter-btn px-3 py-1 text-sm rounded-lg bg-gray-200 hover:bg-blue-500 hover:text-white transition">30 Days</button>
                </div>
            </div>
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
    TEMP_WARNING: 28,
    TEMP_CRITICAL: 35,
    TEMP_MIN: 10,
    SIGNAL_WARNING: 60,
    SIGNAL_CRITICAL: 30,
    SENSOR_TIMEOUT: 30000,
    MAX_HISTORY: 500 // Increased to store more data for 30 days
};

let allDataPoints = []; // Store all data with timestamps
let currentFilter = '24h'; // Default filter
let tempHistory = [];
let signalHistory = [];
let humidityHistory = [];
let labelsHistory = [];
let timestampsHistory = [];
let tempAlertHistory = [];
let signalAlertHistory = [];
let connectionAlertHistory = [];
let historyChart;
let alertChart;

// ============================================
// INIT CHARTS WITH TIME AXIS
// ============================================

function initCharts() {
    // HISTORY CHART with time axis
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
                            let date = new Date(context.raw.x);
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
                            hour: 'HH:mm',
                            day: 'MMM dd',
                            week: 'MMM dd',
                            month: 'MMM yyyy'
                        },
                        tooltipFormat: 'PPpp'
                    },
                    title: {
                        display: true,
                        text: 'Timestamp'
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
            scales: {
                x: {
                    type: 'time',
                    time: {
                        unit: 'hour',
                        displayFormats: {
                            hour: 'HH:mm',
                            day: 'MMM dd',
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
// FILTER DATA BY TIME RANGE
// ============================================

function setTimeFilter(filter) {
    currentFilter = filter;
    
    // Update active button style
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
    
    // Filter data points
    const filteredData = allDataPoints.filter(point => new Date(point.timestamp) >= filterTime);
    
    // Prepare data for charts
    const filteredTemp = [];
    const filteredHumidity = [];
    const filteredSignal = [];
    const filteredTempAlerts = [];
    const filteredSignalAlerts = [];
    const filteredConnAlerts = [];
    const filteredTimestamps = [];
    
    filteredData.forEach(point => {
        filteredTimestamps.push(point.timestamp);
        filteredTemp.push({x: point.timestamp, y: point.temp});
        filteredHumidity.push({x: point.timestamp, y: point.humidity});
        filteredSignal.push({x: point.timestamp, y: point.signal});
        filteredTempAlerts.push({x: point.timestamp, y: point.tempAlert});
        filteredSignalAlerts.push({x: point.timestamp, y: point.signalAlert});
        filteredConnAlerts.push({x: point.timestamp, y: point.connAlert});
    });
    
    // Update history chart
    if (historyChart) {
        historyChart.data.datasets[0].data = filteredTemp;
        historyChart.data.datasets[1].data = filteredHumidity;
        historyChart.data.datasets[2].data = filteredSignal;
        
        // Update x-axis based on time range
        if (currentFilter === '24h') {
            historyChart.options.scales.x.time.unit = 'hour';
            historyChart.options.scales.x.time.displayFormats.hour = 'HH:mm';
        } else if (currentFilter === '7d') {
            historyChart.options.scales.x.time.unit = 'day';
            historyChart.options.scales.x.time.displayFormats.day = 'MMM dd';
        } else {
            historyChart.options.scales.x.time.unit = 'day';
            historyChart.options.scales.x.time.displayFormats.day = 'MMM dd';
        }
        
        historyChart.update();
    }
    
    // Update alert chart
    if (alertChart) {
        alertChart.data.datasets[0].data = filteredTempAlerts;
        alertChart.data.datasets[1].data = filteredSignalAlerts;
        alertChart.data.datasets[2].data = filteredConnAlerts;
        
        if (currentFilter === '24h') {
            alertChart.options.scales.x.time.unit = 'hour';
        } else if (currentFilter === '7d') {
            alertChart.options.scales.x.time.unit = 'day';
        } else {
            alertChart.options.scales.x.time.unit = 'day';
        }
        
        alertChart.update();
    }
    
    // Update data count display
    document.getElementById('dataCount').innerHTML = filteredData.length;
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
    if (isNaN(temp) || isNaN(humidity) || isNaN(signal)) return false;
    if (temp < -20 || temp > 60) return false;
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
        
        const updateTime = new Date(data.created_at);
        if (!isNaN(updateTime.getTime())) {
            document.getElementById('lastUpdate').innerHTML = updateTime.toLocaleString();
        } else {
            document.getElementById('lastUpdate').innerHTML = new Date().toLocaleString();
        }
        
        if (diff > CONFIG.SENSOR_TIMEOUT) {
            setOfflineUI();
            return;
        }
        
        document.getElementById('deviceStatus').innerHTML = 'Online';
        document.getElementById('deviceStatus').className = 'text-2xl font-bold text-green-600';
        document.getElementById('dhtStatus').innerHTML = 'Connected';
        document.getElementById('dhtStatus').className = 'font-bold text-green-600';
        
        const temp = parseFloat(data.temperature);
        const humidity = parseFloat(data.humidity);
        const signal = parseFloat(data.signal_strength);
        
        if (!isValidData(temp, humidity, signal)) {
            console.warn('Invalid data received:', {temp, humidity, signal});
            return;
        }
        
        document.getElementById('currentTemp').innerHTML = temp.toFixed(1) + '°C';
        document.getElementById('currentHumidity').innerHTML = humidity.toFixed(1) + '%';
        document.getElementById('currentSignal').innerHTML = signal.toFixed(0) + '%';
        
        let tempAlert = 0;
        let signalAlert = 0;
        let connectionAlert = 0;
        
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
        
        let aiScore = 100;
        aiScore -= tempAlert * 20;
        aiScore -= signalAlert * 15;
        aiScore = Math.max(0, Math.min(100, aiScore));
        
        document.getElementById('aiScore').innerHTML = aiScore + '%';
        
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
        
        // Store data point with timestamp
        const dataPoint = {
            timestamp: updateTime,
            temp: temp,
            humidity: humidity,
            signal: signal,
            tempAlert: tempAlert,
            signalAlert: signalAlert,
            connAlert: connectionAlert
        };
        
        allDataPoints.push(dataPoint);
        
        // Limit stored data points (keep last 30 days worth of data)
        const thirtyDaysAgo = new Date(now - 30 * 24 * 60 * 60 * 1000);
        allDataPoints = allDataPoints.filter(point => new Date(point.timestamp) >= thirtyDaysAgo);
        
        // Update alert totals
        const tempAlertSum = allDataPoints.filter(p => p.tempAlert > 0).length;
        const signalAlertSum = allDataPoints.filter(p => p.signalAlert > 0).length;
        const connAlertSum = allDataPoints.filter(p => p.connAlert > 0).length;
        
        document.getElementById('totalTempAlerts').innerHTML = tempAlertSum;
        document.getElementById('totalSignalAlerts').innerHTML = signalAlertSum;
        document.getElementById('totalConnAlerts').innerHTML = connAlertSum;
        
        // Apply current filter to update charts
        applyFilter();
        
    } catch(err) {
        console.error('Error loading analytics:', err);
        setOfflineUI();
    }
}

// ============================================
// PROFESSIONAL PDF GENERATION
// ============================================

async function generatePDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    
    // Add header with logo effect
    doc.setFillColor(59, 130, 246);
    doc.rect(0, 0, 210, 40, 'F');
    
    doc.setTextColor(255, 255, 255);
    doc.setFontSize(24);
    doc.setFont('helvetica', 'bold');
    doc.text('ENVIRONET', 20, 25);
    doc.setFontSize(14);
    doc.setFont('helvetica', 'normal');
    doc.text('Environmental Monitoring System', 20, 35);
    
    // Report metadata
    doc.setTextColor(100, 100, 100);
    doc.setFontSize(10);
    const reportDate = new Date().toLocaleString('en-US', {
        dateStyle: 'full',
        timeStyle: 'medium'
    });
    doc.text(`Report Generated: ${reportDate}`, 20, 55);
    doc.text(`User: ${'<?= $username ?>'}`, 20, 62);
    doc.text(`Filter: ${currentFilter === '24h' ? 'Last 24 Hours' : currentFilter === '7d' ? 'Last 7 Days' : 'Last 30 Days'}`, 20, 69);
    
    // Executive Summary Section
    doc.setDrawColor(59, 130, 246);
    doc.setLineWidth(0.5);
    doc.line(20, 80, 190, 80);
    
    doc.setTextColor(0, 0, 0);
    doc.setFontSize(16);
    doc.setFont('helvetica', 'bold');
    doc.text('Executive Summary', 20, 95);
    
    doc.setFontSize(10);
    doc.setFont('helvetica', 'normal');
    
    const tempValue = document.getElementById('currentTemp').innerText;
    const humidityValue = document.getElementById('currentHumidity').innerText;
    const signalValue = document.getElementById('currentSignal').innerText;
    const aiScore = document.getElementById('aiScore').innerText;
    const healthStatus = document.getElementById('aiHealth').innerText;
    const anomalyStatus = document.getElementById('anomalyText').innerText;
    
    const filteredDataCount = allDataPoints.filter(p => {
        const now = new Date();
        let filterTime;
        if (currentFilter === '24h') filterTime = new Date(now - 24 * 60 * 60 * 1000);
        else if (currentFilter === '7d') filterTime = new Date(now - 7 * 24 * 60 * 60 * 1000);
        else filterTime = new Date(now - 30 * 24 * 60 * 60 * 1000);
        return new Date(p.timestamp) >= filterTime;
    }).length;
    
    const summary = [
        `• Current Temperature: ${tempValue}`,
        `• Current Humidity: ${humidityValue}`,
        `• Signal Strength: ${signalValue}`,
        `• AI Health Score: ${aiScore} - System ${healthStatus}`,
        `• Anomaly Detection: ${anomalyStatus}`,
        `• Total Data Points (Selected Period): ${filteredDataCount}`
    ];
    
    let yPos = 110;
    summary.forEach(line => {
        doc.text(line, 25, yPos);
        yPos += 7;
    });
    
    // Current Readings Table
    yPos += 5;
    doc.setFontSize(14);
    doc.setFont('helvetica', 'bold');
    doc.text('Current Sensor Readings', 20, yPos);
    
    const readingsData = [
        ['Parameter', 'Value', 'Status', 'Normal Range'],
        ['Temperature', tempValue, document.getElementById('tempStatus').innerText, '20-28°C'],
        ['Humidity', humidityValue, 'Active', '40-70%'],
        ['Signal Strength', signalValue, document.getElementById('signalStatus').innerText, '>60%'],
        ['AI Score', aiScore, healthStatus, '>80% Excellent']
    ];
    
    doc.autoTable({
        startY: yPos + 5,
        head: [readingsData[0]],
        body: readingsData.slice(1),
        theme: 'striped',
        headStyles: { fillColor: [59, 130, 246], textColor: 255, fontStyle: 'bold' },
        alternateRowStyles: { fillColor: [240, 248, 255] },
        margin: { left: 20, right: 20 }
    });
    
    // Alert Statistics Section
    let finalY = doc.lastAutoTable.finalY + 10;
    
    doc.setFontSize(14);
    doc.setFont('helvetica', 'bold');
    doc.text('Alert Statistics', 20, finalY);
    
    const alertData = [
        ['Alert Type', 'Count', 'Severity Level', 'Recommendation'],
        ['Temperature Alerts', document.getElementById('totalTempAlerts').innerText, 
         parseInt(document.getElementById('totalTempAlerts').innerText) > 10 ? 'High' : 
         parseInt(document.getElementById('totalTempAlerts').innerText) > 0 ? 'Medium' : 'Low',
         'Check ventilation and cooling systems'],
        ['Signal Alerts', document.getElementById('totalSignalAlerts').innerText,
         parseInt(document.getElementById('totalSignalAlerts').innerText) > 10 ? 'High' : 
         parseInt(document.getElementById('totalSignalAlerts').innerText) > 0 ? 'Medium' : 'Low',
         'Verify network stability and interference'],
        ['Connection Alerts', document.getElementById('totalConnAlerts').innerText,
         parseInt(document.getElementById('totalConnAlerts').innerText) > 5 ? 'High' : 
         parseInt(document.getElementById('totalConnAlerts').innerText) > 0 ? 'Medium' : 'Low',
         'Check device connectivity and power supply']
    ];
    
    doc.autoTable({
        startY: finalY + 5,
        head: [alertData[0]],
        body: alertData.slice(1),
        theme: 'striped',
        headStyles: { fillColor: [139, 92, 246], textColor: 255, fontStyle: 'bold' },
        alternateRowStyles: { fillColor: [245, 243, 255] },
        margin: { left: 20, right: 20 }
    });
    
    // Footer
    const pageCount = doc.internal.getNumberOfPages();
    for(let i = 1; i <= pageCount; i++) {
        doc.setPage(i);
        doc.setFontSize(8);
        doc.setTextColor(150, 150, 150);
        doc.text(`ENVIRONET Analytics Report - Page ${i} of ${pageCount}`, 20, doc.internal.pageSize.height - 10);
        doc.text(`Generated by ${'<?= $username ?>'} - ${new Date().toLocaleDateString()}`, 20, doc.internal.pageSize.height - 5);
    }
    
    const filename = `ENVIRONET_Report_${new Date().toISOString().slice(0,19).replace(/:/g, '-')}.pdf`;
    doc.save(filename);
    
    showNotification('PDF Report generated successfully!', 'success');
}

function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `fixed top-20 right-6 z-50 px-6 py-3 rounded-lg shadow-lg text-white ${
        type === 'success' ? 'bg-green-500' : 'bg-red-500'
    } transition-all duration-300 transform translate-x-full`;
    notification.innerHTML = `
        <div class="flex items-center gap-2">
            <span>${type === 'success' ? '✓' : '⚠'}</span>
            <span>${message}</span>
        </div>
    `;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    setTimeout(() => {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// ============================================
// START REAL TIME
// ============================================

document.addEventListener('DOMContentLoaded', () => {
    initCharts();
    setTimeFilter('24h'); // Set default filter to 24 hours
    loadAnalytics();
    setInterval(() => {
        loadAnalytics();
    }, 5000);
});
</script>
</body>
</html>
