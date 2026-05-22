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
<meta name="viewport"
content="width=device-width, initial-scale=1.0">

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
            <h1 class="text-3xl font-bold text-blue-700">
                ENVIRONET Analytics
            </h1>

            <p class="text-gray-500">
                Real Time IoT Monitoring
            </p>
        </div>

        <button
        onclick="generatePDF()"
        class="bg-purple-700 text-white px-5 py-2 rounded-lg">
            PDF Report
        </button>

    </div>

    <!-- TOP CARDS -->

    <div class="grid md:grid-cols-4 gap-5 mb-6">

        <!-- DEVICE -->

        <div class="card">

            <h2 class="font-bold mb-4">
                📡 Device Status
            </h2>

            <p class="text-gray-500 text-sm">
                State
            </p>

            <p id="deviceStatus"
            class="text-2xl font-bold text-green-600">
                Online
            </p>

            <p class="text-gray-500 text-sm mt-3">
                Last Update
            </p>

            <p id="lastUpdate"
            class="font-bold">
                --
            </p>

        </div>

        <!-- TEMPERATURE -->

        <div class="card">

            <h2 class="font-bold mb-4">
                🌡 Temperature
            </h2>

            <p id="currentTemp"
            class="text-4xl font-black text-orange-600">
                --°C
            </p>

            <p id="tempStatus"
            class="mt-2 font-bold text-green-600">
                Normal
            </p>

        </div>

        <!-- HUMIDITY -->

        <div class="card">

            <h2 class="font-bold mb-4">
                💧 Humidity
            </h2>

            <p id="currentHumidity"
            class="text-4xl font-black text-blue-600">
                --%
            </p>

            <p class="mt-2 font-bold text-blue-500">
                DHT11 Sensor
            </p>

        </div>

        <!-- SIGNAL -->

        <div class="card">

            <h2 class="font-bold mb-4">
                📶 Network
            </h2>

            <p id="currentSignal"
            class="text-4xl font-black text-indigo-600">
                --%
            </p>

            <p id="signalStatus"
            class="mt-2 font-bold text-green-600">
                Excellent
            </p>

        </div>

    </div>

    <!-- AI SECTION -->

    <div class="card mb-6">

        <h2 class="text-2xl font-bold mb-5">
            🧠 Artificial Intelligence
        </h2>

        <div class="grid md:grid-cols-4 gap-4">

            <div class="bg-gray-50 rounded-xl p-4">

                <p class="text-gray-500 text-sm">
                    AI Score
                </p>

                <p id="aiScore"
                class="text-3xl font-bold text-purple-700">
                    --
                </p>

            </div>

            <div class="bg-gray-50 rounded-xl p-4">

                <p class="text-gray-500 text-sm">
                    Health
                </p>

                <p id="healthText"
                class="text-3xl font-bold text-green-600">
                    --
                </p>

            </div>

            <div class="bg-gray-50 rounded-xl p-4">

                <p class="text-gray-500 text-sm">
                    Anomaly
                </p>

                <p id="anomalyText"
                class="text-3xl font-bold text-orange-600">
                    --
                </p>

            </div>

            <div class="bg-gray-50 rounded-xl p-4">

                <p class="text-gray-500 text-sm">
                    Data Points
                </p>

                <p id="dataCount"
                class="text-3xl font-bold text-blue-600">
                    0
                </p>

            </div>

        </div>

    </div>

    <!-- CHARTS -->

    <div class="grid lg:grid-cols-2 gap-6">

        <div class="card">

            <h2 class="font-bold mb-4">
                📈 Temperature History
            </h2>

            <div class="chart-box">
                <canvas id="tempChart"></canvas>
            </div>

        </div>

        <div class="card">

            <h2 class="font-bold mb-4">
                📶 Signal History
            </h2>

            <div class="chart-box">
                <canvas id="signalChart"></canvas>
            </div>

        </div>

    </div>

</div>

<script>

// ============================================
// REAL TIME DHT11 + ALERT ANALYTICS SYSTEM
// ============================================

const CONFIG = {

    TEMP_WARNING: 24,
    TEMP_CRITICAL: 28,

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

    const hctx =
        document.getElementById(
            'historyChart'
        ).getContext('2d');

    historyChart =
        new Chart(hctx, {

        type: 'line',

        data: {

            labels: [],

            datasets: [

                {
                    label: 'Temperature °C',

                    data: [],

                    borderColor: '#f97316',

                    backgroundColor:
                    'rgba(249,115,22,0.1)',

                    fill: true,

                    tension: 0.4
                },

                {
                    label: 'Humidity %',

                    data: [],

                    borderColor: '#06b6d4',

                    backgroundColor:
                    'rgba(6,182,212,0.1)',

                    fill: true,

                    tension: 0.4
                },

                {
                    label: 'Signal %',

                    data: [],

                    borderColor: '#2563eb',

                    backgroundColor:
                    'rgba(37,99,235,0.1)',

                    fill: true,

                    tension: 0.4
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
                }
            }
        }
    });

    // ALERT CHART

    const actx =
        document.getElementById(
            'alertChart'
        ).getContext('2d');

    alertChart =
        new Chart(actx, {

        type: 'line',

        data: {

            labels: [],

            datasets: [

                {
                    label: 'Temperature Alerts',

                    data: [],

                    borderColor: '#ef4444',

                    backgroundColor:
                    'rgba(239,68,68,0.1)',

                    fill: true,

                    tension: 0.4
                },

                {
                    label: 'Signal Alerts',

                    data: [],

                    borderColor: '#f59e0b',

                    backgroundColor:
                    'rgba(245,158,11,0.1)',

                    fill: true,

                    tension: 0.4
                },

                {
                    label: 'Connection Alerts',

                    data: [],

                    borderColor: '#8b5cf6',

                    backgroundColor:
                    'rgba(139,92,246,0.1)',

                    fill: true,

                    tension: 0.4
                }
            ]
        },

        options: {

            responsive: true,

            maintainAspectRatio: false
        }
    });
}

// ============================================
// OFFLINE UI
// ============================================

function setOfflineUI() {

    // DEVICE

    document.getElementById(
        'deviceStatus'
    ).innerHTML = 'Offline';

    document.getElementById(
        'deviceStatus'
    ).className =
    'text-red-600 font-bold';

    // DHT11

    document.getElementById(
        'dhtStatus'
    ).innerHTML = 'Disconnected';

    document.getElementById(
        'dhtStatus'
    ).className =
    'text-red-600 font-bold';

    document.getElementById(
        'dhtIcon'
    ).innerHTML = '🔴';

    // VALUES

    document.getElementById(
        'currentTemp'
    ).innerHTML = '--°C';

    document.getElementById(
        'currentHumidity'
    ).innerHTML = '--%';

    document.getElementById(
        'currentSignal'
    ).innerHTML = '--%';

    document.getElementById(
        'anomalyText'
    ).innerHTML = 'Offline';

    document.getElementById(
        'aiHealth'
    ).innerHTML = 'Offline';
}

// ============================================
// LOAD REAL TIME DATA
// ============================================

async function loadAnalytics() {

    try {

        const response =
            await fetch(
                `get_latest_data.php?user_id=<?= $user_id ?>`
            );

        const json =
            await response.json();

        if (
            !json.success
            ||
            !json.data
        ) {

            setOfflineUI();

            return;
        }

        const data =
            json.data;

        const now =
            Date.now();

        const createdAt =
            new Date(
                data.created_at
            ).getTime();

        const diff =
            now - createdAt;

        // ====================================
        // SENSOR OFFLINE
        // ====================================

        if (
            diff >
            CONFIG.SENSOR_TIMEOUT
        ) {

            setOfflineUI();

            connectionAlertHistory.push(1);

            return;
        }

        // ====================================
        // SENSOR ONLINE
        // ====================================

        document.getElementById(
            'deviceStatus'
        ).innerHTML = 'Online';

        document.getElementById(
            'deviceStatus'
        ).className =
        'text-green-600 font-bold';

        document.getElementById(
            'dhtStatus'
        ).innerHTML = 'Connected';

        document.getElementById(
            'dhtStatus'
        ).className =
        'text-green-600 font-bold';

        document.getElementById(
            'dhtIcon'
        ).innerHTML = '🟢';

        // ====================================
        // VALUES
        // ====================================

        const temp =
            parseFloat(
                data.temperature
            );

        const humidity =
            parseFloat(
                data.humidity
            );

        const signal =
            parseFloat(
                data.signal_strength
            );

        document.getElementById(
            'currentTemp'
        ).innerHTML =
        temp.toFixed(1) + '°C';

        document.getElementById(
            'currentHumidity'
        ).innerHTML =
        humidity.toFixed(1) + '%';

        document.getElementById(
            'currentSignal'
        ).innerHTML =
        signal.toFixed(0) + '%';

        document.getElementById(
            'lastUpdate'
        ).innerHTML =
        new Date(
            data.created_at
        ).toLocaleTimeString();

        // ====================================
        // ALERT DETECTION
        // ====================================

        let tempAlert = 0;
        let signalAlert = 0;
        let connectionAlert = 0;

        // TEMP ALERT

        if (
            temp >= CONFIG.TEMP_CRITICAL
        ) {

            tempAlert = 2;

            document.getElementById(
                'tempStatus'
            ).innerHTML = 'Critical';

            document.getElementById(
                'tempStatus'
            ).className =
            'text-red-600 font-bold';

        } else if (
            temp >= CONFIG.TEMP_WARNING
        ) {

            tempAlert = 1;

            document.getElementById(
                'tempStatus'
            ).innerHTML = 'Warning';

            document.getElementById(
                'tempStatus'
            ).className =
            'text-orange-600 font-bold';

        } else {

            document.getElementById(
                'tempStatus'
            ).innerHTML = 'Normal';

            document.getElementById(
                'tempStatus'
            ).className =
            'text-green-600 font-bold';
        }

        // SIGNAL ALERT

        if (
            signal <
            CONFIG.SIGNAL_CRITICAL
        ) {

            signalAlert = 2;

            document.getElementById(
                'signalStatus'
            ).innerHTML = 'Critical';

            document.getElementById(
                'signalStatus'
            ).className =
            'text-red-600 font-bold';

        } else if (
            signal <
            CONFIG.SIGNAL_WARNING
        ) {

            signalAlert = 1;

            document.getElementById(
                'signalStatus'
            ).innerHTML = 'Weak';

            document.getElementById(
                'signalStatus'
            ).className =
            'text-orange-600 font-bold';

        } else {

            document.getElementById(
                'signalStatus'
            ).innerHTML = 'Excellent';

            document.getElementById(
                'signalStatus'
            ).className =
            'text-green-600 font-bold';
        }

        // ====================================
        // AI
        // ====================================

        let aiScore = 100;

        aiScore -= tempAlert * 25;

        aiScore -= signalAlert * 25;

        aiScore = Math.max(0, aiScore);

        document.getElementById(
            'aiScore'
        ).innerHTML =
        aiScore + '%';

        if (aiScore >= 80) {

            document.getElementById(
                'aiHealth'
            ).innerHTML = 'Excellent';

            document.getElementById(
                'aiHealth'
            ).className =
            'text-green-600 font-bold';

        } else if (aiScore >= 50) {

            document.getElementById(
                'aiHealth'
            ).innerHTML = 'Warning';

            document.getElementById(
                'aiHealth'
            ).className =
            'text-orange-600 font-bold';

        } else {

            document.getElementById(
                'aiHealth'
            ).innerHTML = 'Critical';

            document.getElementById(
                'aiHealth'
            ).className =
            'text-red-600 font-bold';
        }

        // ====================================
        // ANOMALY
        // ====================================

        if (
            tempAlert === 2
            ||
            signalAlert === 2
        ) {

            document.getElementById(
                'anomalyText'
            ).innerHTML =
            'Critical';

        } else if (
            tempAlert === 1
            ||
            signalAlert === 1
        ) {

            document.getElementById(
                'anomalyText'
            ).innerHTML =
            'Warning';

        } else {

            document.getElementById(
                'anomalyText'
            ).innerHTML =
            'Normal';
        }

        // ====================================
        // HISTORY
        // ====================================

        tempHistory.push(temp);

        humidityHistory.push(humidity);

        signalHistory.push(signal);

        labelsHistory.push(
            new Date().toLocaleTimeString()
        );

        tempAlertHistory.push(tempAlert);

        signalAlertHistory.push(signalAlert);

        connectionAlertHistory.push(connectionAlert);

        // LIMIT

        if (
            tempHistory.length >
            CONFIG.MAX_HISTORY
        ) {

            tempHistory.shift();

            humidityHistory.shift();

            signalHistory.shift();

            labelsHistory.shift();

            tempAlertHistory.shift();

            signalAlertHistory.shift();

            connectionAlertHistory.shift();
        }

        // ====================================
        // UPDATE HISTORY CHART
        // ====================================

        historyChart.data.labels =
            labelsHistory;

        historyChart.data.datasets[0].data =
            tempHistory;

        historyChart.data.datasets[1].data =
            humidityHistory;

        historyChart.data.datasets[2].data =
            signalHistory;

        historyChart.update();

        // ====================================
        // UPDATE ALERT CHART
        // ====================================

        alertChart.data.labels =
            labelsHistory;

        alertChart.data.datasets[0].data =
            tempAlertHistory;

        alertChart.data.datasets[1].data =
            signalAlertHistory;

        alertChart.data.datasets[2].data =
            connectionAlertHistory;

        alertChart.update();

        // ====================================
        // COUNTERS
        // ====================================

        document.getElementById(
            'totalTempAlerts'
        ).innerHTML =
        tempAlertHistory.reduce(
            (a,b)=>a+b,
            0
        );

        document.getElementById(
            'totalSignalAlerts'
        ).innerHTML =
        signalAlertHistory.reduce(
            (a,b)=>a+b,
            0
        );

        document.getElementById(
            'totalConnAlerts'
        ).innerHTML =
        connectionAlertHistory.reduce(
            (a,b)=>a+b,
            0
        );

    } catch(err) {

        console.error(err);

        setOfflineUI();
    }
}

// ============================================
// START REAL TIME
// ============================================

document.addEventListener(
    'DOMContentLoaded',
    () => {

        initCharts();

        loadAnalytics();

        setInterval(() => {

            loadAnalytics();

        }, 5000);
    }
);

</script>

</body>
</html>
