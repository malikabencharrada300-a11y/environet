<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Analytics - IoT Monitoring</title>

    <!-- Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom@2.0.1/dist/chartjs-plugin-zoom.min.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

    <style>
        .status-online {
            color: #10B981;
        }

        .status-offline {
            color: #EF4444;
        }

        .status-warning {
            color: #F59E0B;
        }

        .status-critical {
            color: #DC2626;
        }

        .status-good {
            color: #10B981;
        }

        .chart-container {
            position: relative;
            width: 100%;
            height: 200px;
        }

        .pdf-loading {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
            z-index: 9999;
        }

        .fade-in {
            animation: fadeIn .5s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .glass {
            backdrop-filter: blur(10px);
            background: rgba(255,255,255,0.85);
        }

        .smooth-hover {
            transition: all .3s ease;
        }

        .smooth-hover:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 18px rgba(0,0,0,0.08);
        }

        canvas {
            user-select: none;
        }

        button {
            transition: all .25s ease;
        }

        button:hover {
            transform: translateY(-1px);
        }

        select {
            outline: none;
        }

        #insightsContainer p,
        #predictionsContainer p,
        #recommendationsContainer p {
            padding: 6px 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }

        #insightsContainer p:last-child,
        #predictionsContainer p:last-child,
        #recommendationsContainer p:last-child {
            border-bottom: none;
        }

        .ai-score-high {
            color: #10B981;
            font-weight: 700;
        }

        .ai-score-medium {
            color: #F59E0B;
            font-weight: 700;
        }

        .ai-score-low {
            color: #DC2626;
            font-weight: 700;
        }

        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-thumb {
            background: #CBD5E1;
            border-radius: 8px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #94A3B8;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-blue-50 min-h-screen">

    <div id="pdfLoading" class="pdf-loading">
        <div class="text-center">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-purple-600 mx-auto mb-4"></div>
            <p class="text-gray-700 font-semibold">Generating PDF report...</p>
        </div>
    </div>

    <div class="container mx-auto p-6 max-w-7xl">

        <div class="bg-white shadow-sm rounded-2xl px-6 py-4 mb-8 flex items-center justify-between">

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

            <div class="flex items-center gap-5">

                <button onclick="generatePDFReport()"
                        class="bg-purple-600 hover:bg-purple-700 text-white px-5 py-2 rounded-lg shadow font-semibold transition">
                    📄 Generate PDF Report
                </button>

                <div class="flex items-center gap-2 text-sm font-semibold text-green-600">
                    <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                    <span id="liveStatusText">Real-time</span>
                </div>

                <div class="flex items-center gap-3 bg-gray-100 px-4 py-2 rounded-full shadow-sm">
                    <span class="text-sm font-semibold text-blue-900">
                        <?php echo htmlspecialchars($username); ?>
                    </span>

                    <div class="w-9 h-9 rounded-full bg-blue-900 text-white flex items-center justify-center font-bold">
                        <?php echo strtoupper(substr($username,0,1)); ?>
                    </div>
                </div>

            </div>
        </div>


        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">

            <div class="bg-white rounded-xl shadow-lg p-6">

                <div class="flex justify-between mb-4">
                    <h2 class="font-bold text-lg text-gray-800">Device Status</h2>
                    <span id="deviceStatusIcon" class="text-2xl">📡</span>
                </div>

                <div class="space-y-3">
                    <div class="flex items-center space-x-2">
                        <span class="text-gray-600">Status:</span>
                        <span id="deviceStatus" class="font-semibold text-gray-400">Checking...</span>
                    </div>

                    <div class="flex items-center space-x-2">
                        <span class="text-gray-600">Last Activity:</span>
                        <span id="lastSeen" class="font-mono text-sm">--:--:--</span>
                    </div>

                    <div class="flex items-center space-x-2">
                        <span class="text-gray-600">Uptime:</span>
                        <span id="uptime" class="font-mono text-sm">--</span>
                    </div>
                </div>

            </div>


            <div class="bg-white rounded-xl shadow-lg p-6">

                <div class="flex justify-between mb-4">
                    <h2 class="font-bold text-lg text-gray-800">Temperature Analysis</h2>
                    <span id="tempIcon" class="text-2xl">🌡️</span>
                </div>

                <div class="mb-4">
                    <p class="text-gray-600">Trend:
                        <span id="tempTrend" class="font-semibold">Analyzing...</span>
                    </p>

                    <p class="text-gray-600 mt-1">Current Value:
                        <span id="currentTemp" class="font-bold text-xl">--°C</span>
                    </p>
                </div>

                <div class="chart-container">
                    <canvas id="tempTrendChart"></canvas>
                </div>

            </div>


            <div class="bg-white rounded-xl shadow-lg p-6">

                <div class="flex justify-between mb-4">
                    <h2 class="font-bold text-lg text-gray-800">Network Analysis</h2>
                    <span id="signalIcon" class="text-2xl">📶</span>
                </div>

                <div class="mb-4">
                    <p class="text-gray-600">Signal:
                        <span id="signalTrend" class="font-semibold">Analyzing...</span>
                    </p>

                    <p class="text-gray-600 mt-1">Strength:
                        <span id="currentSignal" class="font-bold text-xl">--%</span>
                    </p>
                </div>

                <div class="chart-container">
                    <canvas id="signalTrendChart"></canvas>
                </div>

            </div>

        </div>


        <div class="bg-gradient-to-r from-purple-50 to-blue-50 rounded-xl shadow-lg p-6 mb-6">

            <div class="flex justify-between mb-6">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">Artificial Intelligence</h2>
                    <p class="text-sm text-gray-600">Predictive analysis & anomalies</p>
                </div>

                <span id="aiStatus" class="text-sm font-semibold text-green-600">● Active</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                <div class="bg-white rounded-lg p-4 shadow">
                    <h3 class="font-bold text-purple-700 mb-3">Insights</h3>
                    <div id="insightsContainer" class="space-y-2 text-sm"></div>
                </div>

                <div class="bg-white rounded-lg p-4 shadow">
                    <h3 class="font-bold text-blue-700 mb-3">Predictions</h3>
                    <div id="predictionsContainer" class="space-y-2 text-sm"></div>
                </div>

                <div class="bg-white rounded-lg p-4 shadow">
                    <h3 class="font-bold text-green-700 mb-3">Recommendations</h3>
                    <div id="recommendationsContainer" class="space-y-2 text-sm"></div>
                </div>

            </div>

        </div>


        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <div class="bg-white rounded-xl shadow-lg p-6">

                <div class="flex justify-between mb-4">
                    <h3 class="font-bold text-lg">📊 History Chart</h3>

                    <div class="flex space-x-2">
                        <button onclick="switchHistoryView('24h')" class="px-3 py-1 text-sm rounded bg-blue-100">24h</button>
                        <button onclick="switchHistoryView('7d')" class="px-3 py-1 text-sm rounded bg-gray-100">7d</button>
                        <button onclick="switchHistoryView('30d')" class="px-3 py-1 text-sm rounded bg-gray-100">30d</button>
                    </div>
                </div>

                <div class="chart-container" style="height:300px;">
                    <canvas id="historyChart"></canvas>
                </div>

                <div class="mt-4 flex justify-between text-sm text-gray-600">
                    <span>Min: <span id="historyMin">--</span></span>
                    <span>Max: <span id="historyMax">--</span></span>
                    <span>Avg: <span id="historyAvg">--</span></span>
                </div>

                <div class="mt-8">
                    <h4 class="font-semibold text-purple-700 mb-2">🔮 24h Forecast</h4>
                    <div class="chart-container" style="height:220px;">
                        <canvas id="predictiveChart"></canvas>
                    </div>
                </div>

            </div>


            <div class="bg-white rounded-xl shadow-lg p-6">

                <div class="flex justify-between mb-4">
                    <h3 class="font-bold text-lg">⚠️ Alert Chart</h3>

                    <select id="alertTypeFilter"
                            onchange="filterAlerts()"
                            class="px-3 py-1 text-sm rounded border border-gray-300">

                        <option value="all">All</option>
                        <option value="temperature">Temperature</option>
                        <option value="signal">Signal</option>
                        <option value="connection">Connection</option>

                    </select>
                </div>

                <div class="chart-container" style="height:300px;">
                    <canvas id="alertChart"></canvas>
                </div>

                <div class="mt-4">
                    <p class="text-sm text-gray-600">
                        Today's alerts:
                        <span id="todayAlerts" class="font-bold text-red-600">0</span>
                    </p>
                </div>

            </div>

        </div>

    </div>

    <script>
const CONFIG = {
    UPDATE_INTERVAL: 5000,
    OFFLINE_THRESHOLD: 30000,
    TEMP_CRITICAL: 28,
    TEMP_WARNING: 24,
    SIGNAL_CRITICAL: 30,
    SIGNAL_WARNING: 50
};

const state = {
    charts: {},
    lastUpdate: Date.now(),
    currentPeriod: '24h',
    alertView: 'bar'
};

function safeNum(v){
    const n = parseFloat(v);
    return isNaN(n) ? 0 : n;
}

function initCharts() {

    // Temperature chart
    state.charts.temp = new Chart(document.getElementById('tempTrendChart'), {
        type: 'line',
        data: { labels: [], datasets: [{
            data: [],
            borderColor: '#f97316',
            backgroundColor: 'rgba(249,115,22,.12)',
            fill: true,
            tension: .4
        }]},
        options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}} }
    });

    // Signal chart
    state.charts.signal = new Chart(document.getElementById('signalTrendChart'), {
        type: 'line',
        data: { labels: [], datasets: [{
            data: [],
            borderColor: '#2563eb',
            backgroundColor: 'rgba(37,99,235,.12)',
            fill: true,
            tension: .4
        }]},
        options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}} }
    });

    // History chart
    state.charts.history = new Chart(document.getElementById('historyChart'), {
        type: 'line',
        data: {
            labels: [],
            datasets: [
                {
                    label: 'Temperature °C',
                    data: [],
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245,158,11,.08)',
                    yAxisID: 'y',
                    tension: .4,
                    fill: true
                },
                {
                    label: 'Signal %',
                    data: [],
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59,130,246,.08)',
                    yAxisID: 'y1',
                    tension: .4,
                    fill: true
                }
            ]
        },
        options: {
            responsive:true,
            maintainAspectRatio:false,
            interaction:{mode:'index',intersect:false},
            plugins:{
                zoom:{
                    zoom:{wheel:{enabled:true}, pinch:{enabled:true}, mode:'x'},
                    pan:{enabled:true, mode:'x'}
                }
            },
            scales:{
                y:{position:'left'},
                y1:{position:'right',grid:{drawOnChartArea:false}}
            }
        }
    });

    // Alert chart
    state.charts.alert = new Chart(document.getElementById('alertChart'), {
        type:'bar',
        data:{
            labels:[],
            datasets:[{
                label:'Alerts',
                data:[],
                backgroundColor:[]
            }]
        },
        options:{
            responsive:true,
            maintainAspectRatio:false
        }
    });

    // Prediction chart
    state.charts.predict = new Chart(document.getElementById('predictChart'), {
        type:'line',
        data:{
            labels:[],
            datasets:[{
                label:'Next 24h Prediction',
                data:[],
                borderColor:'#8b5cf6',
                backgroundColor:'rgba(139,92,246,.10)',
                fill:true,
                tension:.4
            }]
        },
        options:{ responsive:true, maintainAspectRatio:false }
    });
}

function pushMini(chart, value, max=20){
    const now = new Date().toLocaleTimeString();

    chart.data.labels.push(now);
    chart.data.datasets[0].data.push(value);

    while(chart.data.labels.length > max){
        chart.data.labels.shift();
        chart.data.datasets[0].data.shift();
    }

    chart.update();
}

async function switchHistoryView(period){
    state.currentPeriod = period;

    try{
        const r = await fetch(`get_history.php?period=${period}`);
        const j = await r.json();

        if(!j.success) return;

        const rows = j.history.reverse();

        const labels = rows.map(x=>{
            const d = new Date(x.timestamp);
            return period === '7d'
                ? d.toLocaleDateString()
                : d.toLocaleTimeString();
        });

        const temp = rows.map(x=>safeNum(x.temperature));
        const signal = rows.map(x=>safeNum(x.signal_strength));

        state.charts.history.data.labels = labels;
        state.charts.history.data.datasets[0].data = temp;
        state.charts.history.data.datasets[1].data = signal;
        state.charts.history.update();

        if(temp.length){
            const avg = temp.reduce((a,b)=>a+b,0)/temp.length;
            document.getElementById('historyMin').textContent = Math.min(...temp).toFixed(1)+'°C';
            document.getElementById('historyMax').textContent = Math.max(...temp).toFixed(1)+'°C';
            document.getElementById('historyAvg').textContent = avg.toFixed(1)+'°C';
        }

        generatePrediction(temp);

    }catch(e){ console.log(e); }
}

async function updateAlertChart(){

    try{
        const r = await fetch("get_alert_chart.php");
        const j = await r.json();

        if(!j.success) return;

        const labels = j.alerts.map(x=>x.day);
        const values = j.alerts.map(x=>parseInt(x.total));

        state.charts.alert.data.labels = labels;
        state.charts.alert.data.datasets[0].data = values;

        state.charts.alert.data.datasets[0].backgroundColor = values.map(v=>{
            if(v >= 10) return '#dc2626';
            if(v >= 5) return '#f59e0b';
            return '#10b981';
        });

        state.charts.alert.update();

        document.getElementById('todayAlerts').textContent =
            values.reduce((a,b)=>a+b,0);

        calculateAIScore(values);

    }catch(e){ console.log(e); }
}

function calculateAIScore(alerts){

    if(!alerts.length) return;

    const total = alerts.reduce((a,b)=>a+b,0);
    const score = Math.max(0, 100 - total*3);

    document.getElementById('aiScore').textContent = score + '%';

    let status = 'Healthy';
    if(score < 70) status = 'Warning';
    if(score < 50) status = 'Critical';

    document.getElementById('aiHealth').textContent = status;
}

function generatePrediction(history){

    if(history.length < 5) return;

    const avg = history.slice(-5).reduce((a,b)=>a+b,0)/5;

    const labels = [];
    const values = [];

    for(let i=1;i<=24;i++){
        labels.push(i + 'h');
        values.push((avg + (Math.random()*2-1)).toFixed(2));
    }

    state.charts.predict.data.labels = labels;
    state.charts.predict.data.datasets[0].data = values;
    state.charts.predict.update();
}

function detectAnomaly(temp, signal){

    let anomaly = 'Normal';

    if(temp > 30 || signal < 20){
        anomaly = 'Critical anomaly detected';
    } else if(temp > 26 || signal < 40){
        anomaly = 'Potential anomaly';
    }

    document.getElementById('anomalyText').textContent = anomaly;
}

function analyzeTemp(temp){

    document.getElementById('currentTemp').textContent = temp.toFixed(1)+'°C';

    let status='Stable';

    if(temp > CONFIG.TEMP_CRITICAL) status='Critical';
    else if(temp > CONFIG.TEMP_WARNING) status='High';

    document.getElementById('tempTrend').textContent = status;

    pushMini(state.charts.temp, temp);
}

function analyzeSignal(signal){

    document.getElementById('currentSignal').textContent = signal+'%';

    let status='Good';

    if(signal < CONFIG.SIGNAL_CRITICAL) status='Critical';
    else if(signal < CONFIG.SIGNAL_WARNING) status='Weak';

    document.getElementById('signalTrend').textContent = status;

    pushMini(state.charts.signal, signal);
}

function updateDeviceStatus(){

    const diff = Date.now() - state.lastUpdate;
    const online = diff < CONFIG.OFFLINE_THRESHOLD;

    document.getElementById('deviceStatus').textContent = online ? 'Online':'Offline';
    document.getElementById('liveStatusText').textContent = online ? 'Live':'Offline';
}

function updateUptime(){
    const sec = Math.floor((Date.now() - state.lastUpdate)/1000);
    document.getElementById('uptime').textContent = sec + ' sec';
}

async function loadAnalytics(){

    try{
        const r = await fetch("get_latest_data.php?user_id=<?= $user_id ?>");
        const j = await r.json();

        if(!j.success || !j.data) return;

        state.lastUpdate = Date.now();

        const temp = safeNum(j.data.temperature);
        const signal = safeNum(j.data.signal_strength);

        document.getElementById('lastSeen').textContent =
            new Date().toLocaleTimeString();

        analyzeTemp(temp);
        analyzeSignal(signal);
        detectAnomaly(temp, signal);

        document.getElementById('insightsContainer').innerHTML = `
            <p>Temperature: ${temp.toFixed(1)} °C</p>
            <p>Signal: ${signal}%</p>
        `;

        document.getElementById('predictionsContainer').innerHTML = `
            <p>Forecast stable for next hours</p>
        `;

        document.getElementById('recommendationsContainer').innerHTML = `
            <p>System operating normally</p>
        `;

        await updateAlertChart();

    }catch(e){ console.log(e); }
}

async function generatePDFReport() {
    const loadingDiv = document.getElementById('pdfLoading');
    if (loadingDiv) loadingDiv.style.display = 'block';

    try {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('p', 'mm', 'a4');
        const W = 210;
        const H = 297;

        const username = "<?= htmlspecialchars($username) ?>";
        const currentTemp = document.getElementById('currentTemp')?.textContent || '--';
        const currentSignal = document.getElementById('currentSignal')?.textContent || '--';
        const aiScore = document.getElementById('aiScore')?.textContent || '--';
        const anomaly = document.getElementById('anomalyText')?.textContent || '--';
        const deviceStatus = document.getElementById('deviceStatus')?.textContent || '--';

        const scoreNum = parseFloat(aiScore) || 0;
        const tempNum = parseFloat(currentTemp) || 0;
        const signalNum = parseFloat(currentSignal) || 0;

        // ---------- Helpers ----------
        const addHeader = (title) => {
            doc.setFillColor(30, 64, 175);
            doc.rect(0, 0, W, 18, 'F');
            doc.setTextColor(255,255,255);
            doc.setFontSize(15);
            doc.text(title, 15, 12);
        };

        const addFooter = () => {
            const pages = doc.internal.getNumberOfPages();
            for (let i = 1; i <= pages; i++) {
                doc.setPage(i);
                doc.setTextColor(120);
                doc.setFontSize(8);
                doc.text(`Smart IoT Company Report • Page ${i}/${pages}`, 15, 292);
            }
        };

        const card = (x,y,w,h,color,title,val) => {
            doc.setFillColor(...color);
            doc.roundedRect(x,y,w,h,3,3,'F');
            doc.setTextColor(255,255,255);
            doc.setFontSize(10);
            doc.text(title,x+4,y+7);
            doc.setFontSize(13);
            doc.text(String(val),x+4,y+16);
        };

        // ---------- Generate QR ----------
        const qrDiv = document.createElement('div');
        new QRCode(qrDiv, {
            text: window.location.href,
            width: 100,
            height: 100
        });
        await new Promise(r => setTimeout(r, 300));
        const qrImg = qrDiv.querySelector('img')?.src || qrDiv.querySelector('canvas')?.toDataURL();

        // ================= PAGE 1 COVER =================
        doc.setFillColor(15,23,42);
        doc.rect(0,0,W,H,'F');

        // logo
        doc.setFillColor(59,130,246);
        doc.circle(35,35,12,'F');
        doc.setTextColor(255,255,255);
        doc.setFontSize(14);
        doc.text("IoT", 30, 38);

        doc.setFontSize(24);
        doc.text("SMART ANALYTICS", 55, 55);
        doc.text("CORPORATE REPORT", 50, 72);

        doc.setDrawColor(96,165,250);
        doc.line(35,85,175,85);

        doc.setFontSize(11);
        doc.text(`User: ${username}`, 20, 120);
        doc.text(`Generated: ${new Date().toLocaleString()}`, 20, 128);
        doc.text(`Device: ESP32 Smart Monitoring`, 20, 136);

        if (qrImg) doc.addImage(qrImg, 'PNG', 145, 110, 35, 35);

        // ================= PAGE 2 DASHBOARD =================
        doc.addPage();
        addHeader("Executive Dashboard");

        let y = 30;

        card(15,y,42,22,[249,115,22],"TEMP",currentTemp);
        card(62,y,42,22,[37,99,235],"SIGNAL",currentSignal);
        card(109,y,42,22,[139,92,246],"AI SCORE",aiScore);
        card(156,y,39,22,[16,185,129],"STATUS",deviceStatus);

        y += 35;

        // Gauge
        doc.setFontSize(14);
        doc.setTextColor(0);
        doc.text("AI Gauge", 15, y);

        const centerX = 60;
        const centerY = y + 35;
        const radius = 25;

        doc.setDrawColor(220);
        doc.setLineWidth(6);
        doc.arc(centerX, centerY, radius, radius, 180, 360);

        const angle = 180 + (scoreNum * 1.8);
        const rad = angle * Math.PI / 180;
        const x2 = centerX + radius * Math.cos(rad);
        const y2 = centerY + radius * Math.sin(rad);

        doc.setDrawColor(scoreNum > 70 ? 34 : scoreNum > 40 ? 245 : 220, scoreNum > 70 ? 197 : scoreNum > 40 ? 158 : 38, 94);
        doc.line(centerX, centerY, x2, y2);

        doc.setFontSize(16);
        doc.text(aiScore, centerX - 7, centerY + 8);

        // Pie
        const total = 100;
        const risk = 100 - scoreNum;
        const safe = scoreNum;
        const cx = 150;
        const cy = y + 35;
        const r = 20;

        let safeAngle = (safe / total) * 360;

        doc.setFillColor(34,197,94);
        doc.circle(cx, cy, r, 'F');

        doc.setFillColor(239,68,68);
        doc.setDrawColor(239,68,68);

        for(let i=0;i<safeAngle;i+=2){
            const a1 = (i-90)*Math.PI/180;
            const a2 = (i+2-90)*Math.PI/180;
            doc.triangle(
                cx, cy,
                cx + r*Math.cos(a1), cy + r*Math.sin(a1),
                cx + r*Math.cos(a2), cy + r*Math.sin(a2),
                'F'
            );
        }

        // ================= PAGE 3 CHARTS =================
        doc.addPage();
        addHeader("Analytics Charts");
        y = 28;

        const history = document.getElementById('historyChart');
        const alerts = document.getElementById('alertChart');
        const predict = document.getElementById('predictChart');

        doc.text("History",15,y);
        y += 5;
        if(history) doc.addImage(history.toDataURL('image/png'),'PNG',15,y,180,60);
        y += 70;

        doc.text("Alerts",15,y);
        y += 5;
        if(alerts) doc.addImage(alerts.toDataURL('image/png'),'PNG',15,y,180,55);
        y += 65;

        doc.text("Prediction",15,y);
        y += 5;
        if(predict) doc.addImage(predict.toDataURL('image/png'),'PNG',15,y,180,45);

        // ================= PAGE 4 TABLE =================
        doc.addPage();
        addHeader("Technical Metrics");
        y = 30;

        doc.autoTable({
            startY: y,
            head: [['Metric','Value','Evaluation']],
            body: [
                ['Temperature', currentTemp, tempNum > 28 ? 'High' : 'Normal'],
                ['Signal', currentSignal, signalNum < 40 ? 'Weak' : 'Stable'],
                ['AI Score', aiScore, scoreNum > 70 ? 'Healthy' : 'Risk'],
                ['Anomaly', anomaly, anomaly]
            ],
            theme: 'grid',
            headStyles: { fillColor: [30,64,175] }
        });

        // signature
        y = doc.lastAutoTable.finalY + 40;
        doc.line(30,y,90,y);
        doc.text("Technical Supervisor", 42, y + 8);

        doc.line(120,y,180,y);
        doc.text("System Signature", 137, y + 8);

        // ================= PAGE 5 CONCLUSION =================
        doc.addPage();
        addHeader("AI Conclusion");

        y = 35;
        let conclusion = "";

        if(scoreNum >= 80){
            conclusion = "The monitored environment operates within optimal parameters. AI confirms healthy status and predictive stability.";
        } else if(scoreNum >= 50){
            conclusion = "Moderate operational instability detected. Preventive maintenance recommended.";
        } else {
            conclusion = "Critical anomalies detected. Immediate intervention required.";
        }

        doc.setTextColor(0);
        doc.setFontSize(12);
        doc.text(doc.splitTextToSize(conclusion, 170), 15, y);

        y += 30;

        const insights = document.getElementById('insightsContainer')?.innerText || '';
        const pred = document.getElementById('predictionsContainer')?.innerText || '';
        const rec = document.getElementById('recommendationsContainer')?.innerText || '';

        const all = `${insights}\n\n${pred}\n\n${rec}`;
        doc.setFontSize(10);
        doc.text(doc.splitTextToSize(all, 170), 15, y);

        addFooter();

        doc.save(`corporate-report-${Date.now()}.pdf`);

    } catch(e) {
        console.error(e);
        alert("PDF generation failed");
    } finally {
        if (loadingDiv) loadingDiv.style.display = 'none';
    }
}

function init(){
    initCharts();
    switchHistoryView('24h');
    updateAlertChart();
    loadAnalytics();

    setInterval(loadAnalytics, 5000);
    setInterval(updateDeviceStatus, 5000);
    setInterval(updateUptime, 1000);
}

document.addEventListener('DOMContentLoaded', init);
</script>
</body>
</html>
