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

const CONFIG = {

    TEMP_WARNING: 24,
    TEMP_CRITICAL: 28,

    SIGNAL_WARNING: 60,
    SIGNAL_CRITICAL: 30,

    OFFLINE_THRESHOLD: 60000
};

let tempHistory = [];
let signalHistory = [];
let labelsHistory = [];

let tempChart;
let signalChart;

function initCharts() {

    const tctx =
        document.getElementById('tempChart');

    tempChart =
        new Chart(tctx, {

        type:'line',

        data:{
            labels:[],

            datasets:[{

                label:'Temperature °C',

                data:[],

                borderColor:'#f97316',

                backgroundColor:
                'rgba(249,115,22,0.1)',

                fill:true,

                tension:0.4
            }]
        },

        options:{
            responsive:true
        }
    });

    const sctx =
        document.getElementById('signalChart');

    signalChart =
        new Chart(sctx, {

        type:'line',

        data:{
            labels:[],

            datasets:[{

                label:'Signal %',

                data:[],

                borderColor:'#2563eb',

                backgroundColor:
                'rgba(37,99,235,0.1)',

                fill:true,

                tension:0.4
            }]
        },

        options:{
            responsive:true
        }
    });
}

function setOfflineUI() {

    document.getElementById(
        'deviceStatus'
    ).innerHTML = 'Offline';

    document.getElementById(
        'deviceStatus'
    ).className =
    'text-2xl font-bold text-red-600';

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
}

async function loadRealTimeData() {

    try {

        const response =
            await fetch(
                `get_latest_data.php?user_id=<?= $user_id ?>`
            );

        const json =
            await response.json();

        if (!json.success) {

            setOfflineUI();

            return;
        }

        const data = json.data;

        const createdAt =
            new Date(
                data.created_at
            ).getTime();

        const now =
            Date.now();

        if (
            now - createdAt >
            CONFIG.OFFLINE_THRESHOLD
        ) {

            setOfflineUI();

            return;
        }

        // ONLINE

        document.getElementById(
            'deviceStatus'
        ).innerHTML = 'Online';

        document.getElementById(
            'deviceStatus'
        ).className =
        'text-2xl font-bold text-green-600';

        // DATA

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

        // UPDATE UI

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

        // TEMPERATURE STATUS

        if (
            temp >= CONFIG.TEMP_CRITICAL
        ) {

            document.getElementById(
                'tempStatus'
            ).innerHTML = 'Critical';

            document.getElementById(
                'tempStatus'
            ).className =
            'mt-2 font-bold text-red-600';

        } else if (
            temp >= CONFIG.TEMP_WARNING
        ) {

            document.getElementById(
                'tempStatus'
            ).innerHTML = 'Warning';

            document.getElementById(
                'tempStatus'
            ).className =
            'mt-2 font-bold text-orange-600';

        } else {

            document.getElementById(
                'tempStatus'
            ).innerHTML = 'Normal';

            document.getElementById(
                'tempStatus'
            ).className =
            'mt-2 font-bold text-green-600';
        }

        // SIGNAL STATUS

        if (
            signal < CONFIG.SIGNAL_CRITICAL
        ) {

            document.getElementById(
                'signalStatus'
            ).innerHTML = 'Critical';

            document.getElementById(
                'signalStatus'
            ).className =
            'mt-2 font-bold text-red-600';

        } else if (
            signal < CONFIG.SIGNAL_WARNING
        ) {

            document.getElementById(
                'signalStatus'
            ).innerHTML = 'Weak';

            document.getElementById(
                'signalStatus'
            ).className =
            'mt-2 font-bold text-orange-600';

        } else {

            document.getElementById(
                'signalStatus'
            ).innerHTML = 'Excellent';

            document.getElementById(
                'signalStatus'
            ).className =
            'mt-2 font-bold text-green-600';
        }

        // AI

        let score = 100;

        if (
            temp >= CONFIG.TEMP_WARNING
        ) score -= 20;

        if (
            temp >= CONFIG.TEMP_CRITICAL
        ) score -= 30;

        if (
            signal < CONFIG.SIGNAL_WARNING
        ) score -= 20;

        if (
            signal < CONFIG.SIGNAL_CRITICAL
        ) score -= 30;

        score = Math.max(0, score);

        document.getElementById(
            'aiScore'
        ).innerHTML =
        score + '%';

        // HEALTH

        let health = 'Excellent';
        let healthClass =
            'text-3xl font-bold text-green-600';

        if (score < 50) {

            health = 'Critical';

            healthClass =
            'text-3xl font-bold text-red-600';

        } else if (score < 80) {

            health = 'Warning';

            healthClass =
            'text-3xl font-bold text-orange-600';
        }

        document.getElementById(
            'healthText'
        ).innerHTML = health;

        document.getElementById(
            'healthText'
        ).className =
        healthClass;

        // ANOMALY

        let anomaly = 'None';

        if (
            temp >= CONFIG.TEMP_CRITICAL
            ||
            signal < CONFIG.SIGNAL_CRITICAL
        ) {

            anomaly = 'Critical';

        } else if (
            temp >= CONFIG.TEMP_WARNING
            ||
            signal < CONFIG.SIGNAL_WARNING
        ) {

            anomaly = 'Warning';
        }

        document.getElementById(
            'anomalyText'
        ).innerHTML = anomaly;

        // HISTORY

        tempHistory.push(temp);

        signalHistory.push(signal);

        labelsHistory.push(
            new Date().toLocaleTimeString()
        );

        if (tempHistory.length > 20) {

            tempHistory.shift();

            signalHistory.shift();

            labelsHistory.shift();
        }

        // UPDATE CHARTS

        tempChart.data.labels =
            labelsHistory;

        tempChart.data.datasets[0].data =
            tempHistory;

        tempChart.update();

        signalChart.data.labels =
            labelsHistory;

        signalChart.data.datasets[0].data =
            signalHistory;

        signalChart.update();

        // DATA COUNT

        document.getElementById(
            'dataCount'
        ).innerHTML =
        tempHistory.length;

    } catch(err) {

        console.error(err);

        setOfflineUI();
    }
}

async function generatePDF() {

    const { jsPDF } =
        window.jspdf;

    const doc =
        new jsPDF();

    doc.setFontSize(24);

    doc.text(
        'ENVIRONET REPORT',
        20,
        20
    );

    doc.setFontSize(12);

    doc.text(
        `Generated: ${new Date().toLocaleString()}`,
        20,
        35
    );

    doc.text(
        `Temperature: ${
            document.getElementById(
                'currentTemp'
            ).innerText
        }`,
        20,
        50
    );

    doc.text(
        `Humidity: ${
            document.getElementById(
                'currentHumidity'
            ).innerText
        }`,
        20,
        60
    );

    doc.text(
        `Signal: ${
            document.getElementById(
                'currentSignal'
            ).innerText
        }`,
        20,
        70
    );

    const chart1 =
        document.getElementById(
            'tempChart'
        );

    const img1 =
        chart1.toDataURL(
            'image/png'
        );

    doc.addImage(
        img1,
        'PNG',
        15,
        90,
        180,
        70
    );

    doc.addPage();

    const chart2 =
        document.getElementById(
            'signalChart'
        );

    const img2 =
        chart2.toDataURL(
            'image/png'
        );

    doc.addImage(
        img2,
        'PNG',
        15,
        20,
        180,
        70
    );

    doc.save(
        'ENVIRONET_REPORT.pdf'
    );
}

// START

document.addEventListener(
    'DOMContentLoaded',
    () => {

        initCharts();

        loadRealTimeData();

        setInterval(() => {

            loadRealTimeData();

        }, 5000);
    }
);

</script>

</body>
</html>
