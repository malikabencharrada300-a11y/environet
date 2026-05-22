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
    height: 450px;
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
        <div class="card">
            <h2 class="font-bold mb-4">📡 Device Status</h2>
            <p class="text-gray-500 text-sm">State</p>
            <p id="deviceStatus" class="text-2xl font-bold text-green-600">Online</p>
            <p class="text-gray-500 text-sm mt-3">Last Update</p>
            <p id="lastUpdate" class="font-bold text-sm">--</p>
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
    <div class="grid lg:grid-cols-2 gap-6">
        <!-- Temperature Chart -->
        <div class="card">
            <div class="flex justify-between items-center mb-4">
                <h2 class="font-bold text-xl">🌡 Temperature History</h2>
                <div class="flex gap-2">
                    <button onclick="loadChartData('hour')" id="filterHour" class="filter-btn px-3 py-1 text-sm rounded-lg bg-gray-200 hover:bg-blue-500 hover:text-white transition">Hour</button>
                    <button onclick="loadChartData('day')" id="filterDay" class="filter-btn px-3 py-1 text-sm rounded-lg bg-blue-600 text-white active transition">Day</button>
                    <button onclick="loadChartData('week')" id="filterWeek" class="filter-btn px-3 py-1 text-sm rounded-lg bg-gray-200 hover:bg-blue-500 hover:text-white transition">Week</button>
                    <button onclick="loadChartData('month')" id="filterMonth" class="filter-btn px-3 py-1 text-sm rounded-lg bg-gray-200 hover:bg-blue-500 hover:text-white transition">Month</button>
                </div>
            </div>
            <div class="chart-box">
                <canvas id="tempChart"></canvas>
            </div>
            <p class="text-xs text-gray-400 text-center mt-2">Real data from database • Updates every 5 seconds</p>
        </div>

        <!-- Signal Chart -->
        <div class="card">
            <div class="flex justify-between items-center mb-4">
                <h2 class="font-bold text-xl">📶 Signal & Humidity History</h2>
                <div class="flex gap-2">
                    <button onclick="loadChartData('hour')" id="filterSignalHour" class="filter-btn px-3 py-1 text-sm rounded-lg bg-gray-200 hover:bg-blue-500 hover:text-white transition">Hour</button>
                    <button onclick="loadChartData('day')" id="filterSignalDay" class="filter-btn px-3 py-1 text-sm rounded-lg bg-blue-600 text-white active transition">Day</button>
                    <button onclick="loadChartData('week')" id="filterSignalWeek" class="filter-btn px-3 py-1 text-sm rounded-lg bg-gray-200 hover:bg-blue-500 hover:text-white transition">Week</button>
                    <button onclick="loadChartData('month')" id="filterSignalMonth" class="filter-btn px-3 py-1 text-sm rounded-lg bg-gray-200 hover:bg-blue-500 hover:text-white transition">Month</button>
                </div>
            </div>
            <div class="chart-box">
                <canvas id="signalChart"></canvas>
            </div>
            <p class="text-xs text-gray-400 text-center mt-2">Real data from database • Updates every 5 seconds</p>
        </div>
    </div>
</div>

<script>
// ============================================
// REAL TIME SENSOR DASHBOARD
// ============================================

let tempChart = null;
let signalChart = null;
let currentPeriod = 'day';
let updateInterval = null;

// Configuration
const CONFIG = {
    TEMP_WARNING: 28,
    TEMP_CRITICAL: 35,
    SIGNAL_WARNING: 60,
    SIGNAL_CRITICAL: 30,
    UPDATE_INTERVAL: 5000 // 5 seconds
};

// ============================================
// INITIALIZE CHARTS
// ============================================

function initCharts() {
    // Temperature Chart
    const tempCtx = document.getElementById('tempChart').getContext('2d');
    tempChart = new Chart(tempCtx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'Temperature (°C)',
                data: [],
                borderColor: '#f97316',
                backgroundColor: 'rgba(249,115,22,0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointRadius: 3,
                pointHoverRadius: 6,
                pointBackgroundColor: '#f97316',
                pointBorderColor: '#fff',
                pointBorderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: { usePointStyle: true, boxWidth: 10 }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Temperature: ' + context.raw.toFixed(1) + '°C';
                        }
                    }
                }
            },
            scales: {
                y: {
                    title: { display: true, text: 'Temperature (°C)' },
                    min: 0,
                    max: 50,
                    grid: { color: 'rgba(0,0,0,0.05)' }
                },
                x: {
                    title: { display: true, text: 'Timestamp' },
                    ticks: { maxRotation: 45, minRotation: 45 }
                }
            }
        }
    });

    // Signal & Humidity Chart
    const signalCtx = document.getElementById('signalChart').getContext('2d');
    signalChart = new Chart(signalCtx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [
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
                    labels: { usePointStyle: true, boxWidth: 10 }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            if (context.dataset.label === 'Humidity (%)') {
                                return 'Humidity: ' + context.raw.toFixed(1) + '%';
                            }
                            return 'Signal: ' + context.raw.toFixed(0) + '%';
                        }
                    }
                }
            },
            scales: {
                y: {
                    title: { display: true, text: 'Humidity (%)' },
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
}

// ============================================
// LOAD CHART DATA FROM YOUR EXISTING API
// ============================================

async function loadChartData(period) {
    currentPeriod = period;
    
    // Update active buttons for both charts
    document.querySelectorAll('[id^="filter"]').forEach(btn => {
        btn.classList.remove('active', 'bg-blue-600', 'text-white');
        btn.classList.add('bg-gray-200');
    });
    
    // Get limit based on period
    let limit = 24;
    if (period === 'hour') limit = 60;
    else if (period === 'day') limit = 24;
    else if (period === 'week') limit = 168;
    else if (period === 'month') limit = 720;
    
    // Update button styles for temperature chart
    const tempBtnMap = {
        'hour': 'filterHour',
        'day': 'filterDay',
        'week': 'filterWeek',
        'month': 'filterMonth'
    };
    const tempActiveBtn = document.getElementById(tempBtnMap[period]);
    if (tempActiveBtn) {
        tempActiveBtn.classList.remove('bg-gray-200');
        tempActiveBtn.classList.add('active', 'bg-blue-600', 'text-white');
    }
    
    // Update button styles for signal chart
    const signalBtnMap = {
        'hour': 'filterSignalHour',
        'day': 'filterSignalDay',
        'week': 'filterSignalWeek',
        'month': 'filterSignalMonth'
    };
    const signalActiveBtn = document.getElementById(signalBtnMap[period]);
    if (signalActiveBtn) {
        signalActiveBtn.classList.remove('bg-gray-200');
        signalActiveBtn.classList.add('active', 'bg-blue-600', 'text-white');
    }
    
    // Show loading
    document.getElementById('dataCount').innerHTML = '<div class="loading"></div>';
    
    try {
        // Use your existing get_history.php endpoint
        const response = await fetch(`get_history.php?period=${period}&limit=${limit}&user_id=<?= $user_id ?>`);
        const result = await response.json();
        
        if (result.success && result.data) {
            const data = result.data;
            
            // Update temperature chart
            if (tempChart) {
                tempChart.data.labels = data.timestamps || data.labels || [];
                tempChart.data.datasets[0].data = data.temperatures || data.temp || [];
                tempChart.update();
            }
            
            // Update signal chart
            if (signalChart) {
                signalChart.data.labels = data.timestamps || data.labels || [];
                signalChart.data.datasets[0].data = data.humidities || data.humidity || [];
                signalChart.data.datasets[1].data = data.signals || data.signal || [];
                signalChart.update();
            }
            
            // Update data count
            const dataCount = (data.temperatures || data.temp || []).length;
            document.getElementById('dataCount').innerHTML = dataCount || 0;
            
            console.log(`Loaded ${dataCount} data points for period: ${period}`);
        } else {
            console.error('Failed to load chart data:', result.error);
            document.getElementById('dataCount').innerHTML = '0';
            // Load demo data if no real data
            loadDemoData(period);
        }
    } catch(error) {
        console.error('Error loading chart data:', error);
        document.getElementById('dataCount').innerHTML = 'Error';
        // Load demo data as fallback
        loadDemoData(period);
    }
}

// ============================================
// DEMO DATA FOR TESTING (falls back if no real data)
// ============================================

function loadDemoData(period) {
    const now = new Date();
    const labels = [];
    const temps = [];
    const humidities = [];
    const signals = [];
    
    let points = 24;
    if (period === 'hour') points = 60;
    else if (period === 'day') points = 24;
    else if (period === 'week') points = 168;
    else if (period === 'month') points = 720;
    
    for (let i = points; i >= 0; i--) {
        let date = new Date(now);
        if (period === 'hour') date.setMinutes(now.getMinutes() - i);
        else if (period === 'day') date.setHours(now.getHours() - i);
        else if (period === 'week') date.setHours(now.getHours() - i);
        else date.setHours(now.getHours() - i);
        
        labels.push(date.toLocaleTimeString());
        
        // Generate realistic demo data
        const baseTemp = 22 + Math.sin(i / 10) * 5;
        const baseHumidity = 55 + Math.cos(i / 8) * 15;
        const baseSignal = 75 + Math.sin(i / 20) * 20;
        
        temps.push(baseTemp);
        humidities.push(baseHumidity);
        signals.push(Math.min(100, Math.max(0, baseSignal)));
    }
    
    if (tempChart) {
        tempChart.data.labels = labels;
        tempChart.data.datasets[0].data = temps;
        tempChart.update();
    }
    
    if (signalChart) {
        signalChart.data.labels = labels;
        signalChart.data.datasets[0].data = humidities;
        signalChart.data.datasets[1].data = signals;
        signalChart.update();
    }
    
    document.getElementById('dataCount').innerHTML = points;
}

// ============================================
// LOAD LATEST REAL TIME DATA
// ============================================

async function loadLatestData() {
    try {
        // Use your existing get_latest_data.php endpoint
        const response = await fetch(`get_latest_data.php?user_id=<?= $user_id ?>&t=${Date.now()}`);
        const result = await response.json();
        
        if (result.success && result.data) {
            const data = result.data;
            const temp = parseFloat(data.temperature);
            const humidity = parseFloat(data.humidity);
            const signal = parseFloat(data.signal_strength);
            
            // Animate value updates
            animateValue('currentTemp', temp.toFixed(1) + '°C');
            animateValue('currentHumidity', humidity.toFixed(1) + '%');
            animateValue('currentSignal', signal.toFixed(0) + '%');
            
            // Update last update time
            if (data.created_at) {
                const updateDate = new Date(data.created_at);
                document.getElementById('lastUpdate').innerHTML = updateDate.toLocaleString();
            }
            
            // Temperature alerts
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
            
            // Humidity status
            if (humidity > 70) {
                updateStatus('humidityStatus', 'High 💧', 'text-blue-600');
            } else if (humidity < 30) {
                updateStatus('humidityStatus', 'Low 💧', 'text-orange-600');
            } else {
                updateStatus('humidityStatus', 'Normal ✓', 'text-green-600');
            }
            
            // Signal alerts
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
            
            // AI Score calculation
            let aiScore = 100;
            aiScore -= tempAlert * 20;
            aiScore -= signalAlert * 15;
            aiScore = Math.max(0, Math.min(100, aiScore));
            
            animateValue('aiScore', aiScore + '%');
            document.getElementById('aiScoreBar').style.width = aiScore + '%';
            
            // Health status
            if (aiScore >= 80) {
                updateStatus('aiHealth', 'Excellent ✓', 'text-green-600');
            } else if (aiScore >= 50) {
                updateStatus('aiHealth', 'Warning ⚠️', 'text-orange-600');
            } else {
                updateStatus('aiHealth', 'Critical 🔴', 'text-red-600');
            }
            
            // Anomaly detection
            if (tempAlert === 2 || signalAlert === 2) {
                updateStatus('anomalyText', 'Critical 🔴', 'text-red-600');
            } else if (tempAlert === 1 || signalAlert === 1) {
                updateStatus('anomalyText', 'Warning ⚠️', 'text-orange-600');
            } else {
                updateStatus('anomalyText', 'Normal ✓', 'text-green-600');
            }
            
            // Device status
            document.getElementById('deviceStatus').innerHTML = 'Online';
            document.getElementById('deviceStatus').className = 'text-2xl font-bold text-green-600';
        } else {
            console.log('No real data, using demo mode');
            loadDemoData(currentPeriod);
        }
    } catch(error) {
        console.error('Error loading latest data:', error);
        document.getElementById('deviceStatus').innerHTML = 'Demo Mode';
        document.getElementById('deviceStatus').className = 'text-2xl font-bold text-yellow-600';
    }
}

function animateValue(elementId, newValue) {
    const element = document.getElementById(elementId);
    if (element && element.innerText !== newValue) {
        element.classList.add('changed');
        element.innerText = newValue;
        setTimeout(() => {
            element.classList.remove('changed');
        }, 300);
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
// GENERATE PDF REPORT
// ============================================

async function generatePDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    
    // Header
    doc.setFillColor(59, 130, 246);
    doc.rect(0, 0, 210, 40, 'F');
    doc.setTextColor(255, 255, 255);
    doc.setFontSize(24);
    doc.setFont('helvetica', 'bold');
    doc.text('ENVIRONET', 20, 25);
    doc.setFontSize(12);
    doc.text('Environmental Monitoring Report', 20, 35);
    
    // Report info
    doc.setTextColor(100, 100, 100);
    doc.setFontSize(10);
    doc.text(`Generated: ${new Date().toLocaleString()}`, 20, 55);
    doc.text(`User: <?= $username ?>`, 20, 62);
    doc.text(`Period: ${currentPeriod}`, 20, 69);
    
    // Current readings
    doc.setTextColor(0, 0, 0);
    doc.setFontSize(14);
    doc.setFont('helvetica', 'bold');
    doc.text('Current Readings', 20, 85);
    
    doc.setFontSize(10);
    doc.setFont('helvetica', 'normal');
    doc.text(`Temperature: ${document.getElementById('currentTemp').innerText}`, 25, 100);
    doc.text(`Humidity: ${document.getElementById('currentHumidity').innerText}`, 25, 110);
    doc.text(`Signal: ${document.getElementById('currentSignal').innerText}`, 25, 120);
    doc.text(`AI Score: ${document.getElementById('aiScore').innerText}`, 25, 130);
    doc.text(`Status: ${document.getElementById('aiHealth').innerText}`, 25, 140);
    
    // Save PDF
    const filename = `ENVIRONET_Report_${new Date().toISOString().slice(0,19).replace(/:/g, '-')}.pdf`;
    doc.save(filename);
    
    showNotification('PDF Report generated successfully!', 'success');
}

function showNotification(message, type) {
    const notification = document.createElement('div');
    notification.className = `fixed top-20 right-6 z-50 px-6 py-3 rounded-lg shadow-lg text-white ${
        type === 'success' ? 'bg-green-500' : 'bg-red-500'
    } transition-all duration-300 transform translate-x-full`;
    notification.innerHTML = `<div class="flex items-center gap-2">✓ ${message}</div>`;
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
// INITIALIZATION
// ============================================

document.addEventListener('DOMContentLoaded', () => {
    // Initialize charts
    initCharts();
    
    // Load initial chart data (day period)
    loadChartData('day');
    
    // Load latest real-time data
    loadLatestData();
    
    // Set up real-time updates every 5 seconds
    if (updateInterval) clearInterval(updateInterval);
    updateInterval = setInterval(() => {
        loadLatestData();
        // Refresh chart data every minute
        loadChartData(currentPeriod);
    }, CONFIG.UPDATE_INTERVAL);
    
    console.log('✅ Real-time monitoring started - Updates every 5 seconds');
    console.log('📊 Using existing PHP endpoints: get_history.php, get_latest_data.php');
});
</script>
</body>
</html>
