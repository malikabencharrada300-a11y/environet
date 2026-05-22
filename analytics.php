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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Environet | Smart IoT Analytics Dashboard</title>
    
    <!-- Tailwind + Libraries -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom@2.0.1/dist/chartjs-plugin-zoom.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
    
    <style>
        * { scroll-behavior: smooth; }
        body {
            background: #f4f7fc;
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        .glass-card {
            background: rgba(255,255,255,0.96);
            backdrop-filter: blur(0px);
            border-radius: 1.5rem;
            border: 1px solid rgba(59,130,246,0.12);
            box-shadow: 0 8px 20px rgba(0,0,0,0.02), 0 2px 6px rgba(0,0,0,0.03);
            transition: all 0.2s ease;
        }
        .metric-value {
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #1E3A8A 0%, #3B82F6 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .status-badge {
            transition: all 0.2s;
        }
        .chart-container {
            position: relative;
            width: 100%;
            min-height: 200px;
            background: #ffffff;
            border-radius: 1rem;
            padding: 0.5rem;
        }
        .pdf-loading {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0,0,0,0.85);
            backdrop-filter: blur(12px);
            color: white;
            padding: 2rem 3rem;
            border-radius: 2rem;
            z-index: 9999;
            text-align: center;
            font-weight: 600;
            box-shadow: 0 25px 40px rgba(0,0,0,0.3);
        }
        button:active { transform: scale(0.97); }
        .alert-legend-color { width: 28px; height: 4px; border-radius: 4px; }
    </style>
</head>
<body class="min-h-screen p-4 md:p-6">

<div id="pdfLoading" class="pdf-loading">
    <div class="animate-spin rounded-full h-10 w-10 border-4 border-white border-t-transparent mx-auto mb-3"></div>
    <p>Generating PDF report ...</p>
</div>

<div class="max-w-7xl mx-auto">

    <!-- Header -->
    <div class="glass-card p-5 mb-6 flex flex-wrap justify-between items-center">
        <div class="flex items-center gap-4">
            <a href="#" class="bg-gradient-to-r from-blue-700 to-indigo-700 text-white px-5 py-2 rounded-full text-sm font-semibold shadow-md hover:shadow-lg transition">← Dashboard</a>
            <div>
                <h1 class="text-2xl md:text-3xl font-extrabold bg-gradient-to-r from-blue-800 to-purple-700 bg-clip-text text-transparent">⚡ Environet Analytics</h1>
                <p class="text-xs text-gray-500">Real-time sensor intelligence · Predictive monitoring</p>
            </div>
        </div>
        <div class="flex items-center gap-4 mt-3 sm:mt-0">
            <button onclick="generateFullReport()" class="bg-purple-700 hover:bg-purple-800 text-white px-5 py-2 rounded-lg shadow flex items-center gap-2 text-sm font-semibold transition">
                📄 PDF Report
            </button>
            <div class="flex items-center gap-2 bg-gray-100 px-3 py-1.5 rounded-full">
                <span class="relative flex h-2.5 w-2.5">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                </span>
                <span class="text-xs font-medium text-gray-700" id="liveBadge">LIVE</span>
            </div>
            <div class="bg-gray-100 px-3 py-1.5 rounded-full text-sm font-semibold text-gray-800">👤 Admin</div>
        </div>
    </div>

    <!-- 4 Key Cards (Device, DHT11, Temperature, Network) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-6">
        <!-- Device Status Card -->
        <div class="glass-card p-5">
            <div class="flex justify-between items-start"><h2 class="font-bold text-gray-700">📡 Device Status</h2><span class="text-2xl">🖥️</span></div>
            <div class="mt-3 space-y-2">
                <div><span class="text-xs text-gray-500">State</span><p id="deviceState" class="text-xl font-bold text-emerald-600">Online</p></div>
                <div><span class="text-xs text-gray-500">Last Activity</span><p id="lastSeenTime" class="font-mono text-sm font-semibold">--:--:--</p></div>
                <div><span class="text-xs text-gray-500">Uptime</span><p id="uptimeVal" class="font-mono text-sm">-- min</p></div>
            </div>
        </div>
        <!-- DHT11 Sensor -->
        <div class="glass-card p-5">
            <div class="flex justify-between"><h2 class="font-bold text-gray-700">🌡️ DHT11 Sensor</h2><span id="dhtIconEmoji" class="text-2xl">🌿</span></div>
            <div class="mt-3 space-y-2">
                <div><span class="text-xs text-gray-500">Sensor Slave</span><p id="sensorStatus" class="text-lg font-semibold text-emerald-600">Connected</p></div>
                <div><span class="text-xs text-gray-500">Last Reading</span><p id="lastReadingTime" class="font-mono text-sm">--:--:--</p></div>
                <div><span class="text-xs text-gray-500">Load Heating</span><p id="loadHeating" class="text-sm">Idle</p></div>
            </div>
        </div>
        <!-- Temperature Analysis + mini chart -->
        <div class="glass-card p-5">
            <div class="flex justify-between"><h2 class="font-bold text-gray-700">🌡️ Temperature</h2><span id="tempMainIcon" class="text-2xl">🌡️</span></div>
            <div><span class="text-xs text-gray-500">Trend</span><span id="trendLabel" class="ml-2 text-xs font-bold px-2 py-0.5 rounded-full bg-green-100 text-green-700">Normal</span></div>
            <div class="mt-1"><span class="text-3xl font-black" id="currentTempMain">--°C</span></div>
            <div class="chart-container mt-2" style="height: 90px;"><canvas id="sparkTempChart"></canvas></div>
        </div>
        <!-- Network Card -->
        <div class="glass-card p-5">
            <div class="flex justify-between"><h2 class="font-bold text-gray-700">📶 Network</h2><span id="signalIconMain" class="text-2xl">📶</span></div>
            <div><span class="text-xs text-gray-500">Signal</span><span id="signalQuality" class="ml-2 text-xs font-bold px-2 py-0.5 rounded-full bg-green-100 text-green-700">Excellent</span></div>
            <div class="mt-1"><span id="signalStrengthMain" class="text-3xl font-black">--%</span></div>
            <div class="chart-container mt-2" style="height: 90px;"><canvas id="sparkSignalChart"></canvas></div>
        </div>
    </div>

    <!-- AI Section -->
    <div class="glass-card p-6 mb-6">
        <div class="flex flex-wrap justify-between items-center mb-4"><h2 class="text-2xl font-bold bg-gradient-to-r from-indigo-700 to-purple-600 bg-clip-text text-transparent">🧠 Artificial Intelligence</h2><span class="text-xs bg-green-100 text-green-800 px-3 py-1 rounded-full">Predictive Analysis</span></div>
        <div class="grid md:grid-cols-3 gap-6">
            <div class="bg-blue-50/60 p-4 rounded-xl"><p class="font-bold text-blue-800">💡 Insights</p><div id="aiInsights" class="text-sm text-gray-700 mt-2">Loading live data...</div></div>
            <div class="bg-purple-50/60 p-4 rounded-xl"><p class="font-bold text-purple-800">🔮 Predictions</p><div id="aiPredictions" class="text-sm text-gray-700 mt-2">24h stable monitoring expected</div></div>
            <div class="bg-amber-50/60 p-4 rounded-xl"><p class="font-bold text-amber-800">🎯 Recommendations</p><div id="aiRecommendations" class="text-sm text-gray-700 mt-2">System nominal</div></div>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-5">
            <div class="bg-white rounded-xl p-3 text-center shadow-sm"><span class="text-xs text-gray-500">AI Score</span><p id="aiScoreVal" class="text-2xl font-bold text-indigo-600">--%</p></div>
            <div class="bg-white rounded-xl p-3 text-center shadow-sm"><span class="text-xs text-gray-500">Health</span><p id="aiHealthVal" class="text-2xl font-bold text-emerald-600">--</p></div>
            <div class="bg-white rounded-xl p-3 text-center shadow-sm"><span class="text-xs text-gray-500">Anomalies</span><p id="anomalyDetect" class="text-xl font-bold text-gray-600">None</p></div>
            <div class="bg-white rounded-xl p-3 text-center shadow-sm"><span class="text-xs text-gray-500">Data Points</span><p id="dataPointsCount" class="text-2xl font-bold text-blue-600">0</p></div>
        </div>
    </div>

    <!-- Double wide: History Chart + Alert Curves -->
    <div class="grid lg:grid-cols-2 gap-6">
        <!-- History Chart + 24h Prediction -->
        <div class="glass-card p-5">
            <div class="flex justify-between flex-wrap mb-3"><h3 class="font-bold text-gray-800">📊 History Chart</h3><div class="flex gap-2"><button onclick="setHistoryPeriod('24h')" id="btn24h" class="text-xs bg-blue-600 text-white px-3 py-1 rounded-md">24h</button><button onclick="setHistoryPeriod('7d')" id="btn7d" class="text-xs bg-gray-200 px-3 py-1 rounded-md">7d</button><button onclick="setHistoryPeriod('30d')" id="btn30d" class="text-xs bg-gray-200 px-3 py-1 rounded-md">30d</button></div></div>
            <div class="chart-container" style="height: 280px;"><canvas id="mainHistoryChart"></canvas></div>
            <div class="grid grid-cols-3 gap-3 mt-3 text-center"><div class="bg-gray-50 p-2 rounded"><span class="text-xs">Min</span><p id="histMin" class="font-bold text-blue-700">--</p></div><div class="bg-gray-50 p-2 rounded"><span class="text-xs">Max</span><p id="histMax" class="font-bold text-purple-700">--</p></div><div class="bg-gray-50 p-2 rounded"><span class="text-xs">Avg</span><p id="histAvg" class="font-bold text-green-700">--</p></div></div>
            <div class="mt-5"><h4 class="font-bold text-purple-700 text-sm flex items-center gap-1">🔮 24h Prediction (Temperature)</h4><div class="chart-container mt-2" style="height: 200px;"><canvas id="futurePredictionCanvas"></canvas></div></div>
        </div>

        <!-- Alert Curves (3 lines) + counters -->
        <div class="glass-card p-5">
            <h3 class="font-bold text-gray-800 mb-2">⚠️ Alert Curves (last 7 days)</h3>
            <div class="flex gap-4 mb-3 text-xs"><div class="flex items-center gap-1"><div class="alert-legend-color bg-red-500"></div><span>🌡️ Temp Alerts</span></div><div class="flex items-center gap-1"><div class="alert-legend-color bg-amber-500"></div><span>📶 Signal Alerts</span></div><div class="flex items-center gap-1"><div class="alert-legend-color bg-blue-500"></div><span>🔌 Connection Alerts</span></div></div>
            <div class="chart-container" style="height: 280px;"><canvas id="tripleAlertChart"></canvas></div>
            <div class="grid grid-cols-3 gap-2 mt-4 text-center">
                <div class="bg-red-50 rounded-xl p-2"><p class="text-xs">🔥 Temp</p><span id="totalTempAlerts" class="text-2xl font-bold text-red-600">0</span></div>
                <div class="bg-orange-50 rounded-xl p-2"><p class="text-xs">📡 Signal</p><span id="totalSignalAlerts" class="text-2xl font-bold text-orange-600">0</span></div>
                <div class="bg-blue-50 rounded-xl p-2"><p class="text-xs">🔌 Conn.</p><span id="totalConnAlerts" class="text-2xl font-bold text-blue-600">0</span></div>
            </div>
            <div class="mt-3 bg-gradient-to-r from-gray-100 to-gray-50 rounded-xl p-3 text-center"><span class="font-bold">Total alerts (7d): </span><span id="globalAlertsSum" class="text-xl font-black text-red-700">0</span></div>
        </div>
    </div>
</div>

<script>
    // ---------- SIMULATED REAL-TIME DATA (fully functional, charts update, time corrected) ----------
    let lastTimestamp = Date.now();
    let temperatureHistoryArray = [];     // stores {timestamp, temp}
    let signalHistoryArray = [];
    let currentTemp = 23.8;
    let currentSignal = 92;
    let historyPeriod = '24h';
    let charts = {};

    // Helper: generate random plausible variation each cycle
    function generateNewReadings() {
        // gentle walk
        let deltaTemp = (Math.random() - 0.5) * 0.6;
        let newTemp = currentTemp + deltaTemp;
        newTemp = Math.min(38, Math.max(16, newTemp));
        currentTemp = parseFloat(newTemp.toFixed(1));
        
        let deltaSig = (Math.random() - 0.5) * 3;
        let newSig = currentSignal + deltaSig;
        newSig = Math.min(100, Math.max(25, newSig));
        currentSignal = Math.floor(newSig);
        
        const now = Date.now();
        temperatureHistoryArray.push({ timestamp: now, temp: currentTemp });
        signalHistoryArray.push({ timestamp: now, signal: currentSignal });
        if(temperatureHistoryArray.length > 200) temperatureHistoryArray.shift();
        if(signalHistoryArray.length > 200) signalHistoryArray.shift();
        lastTimestamp = now;
        updateUIFromCurrent();
        updateMiniCharts();
        refreshHistoryByPeriod();    // update main history chart based on period
        updateAlertCurvesData();     // dynamic alert curves (counts per day)
        updateAIPredictionsAndRecommendations();
    }
    
    // update top cards
    function updateUIFromCurrent() {
        document.getElementById('currentTempMain').innerHTML = currentTemp.toFixed(1) + '°C';
        document.getElementById('signalStrengthMain').innerHTML = currentSignal + '%';
        document.getElementById('lastSeenTime').innerHTML = new Date().toLocaleTimeString();
        document.getElementById('lastReadingTime').innerHTML = new Date().toLocaleTimeString();
        let uptimeMin = Math.floor((Date.now() - (lastTimestamp - 5000)) / 60000);
        document.getElementById('uptimeVal').innerHTML = (uptimeMin > 0 ? uptimeMin : 0) + ' min';
        document.getElementById('deviceState').innerHTML = 'Online';
        document.getElementById('sensorStatus').innerHTML = 'Connected';
        document.getElementById('loadHeating').innerHTML = currentTemp > 26 ? 'Active' : 'Idle';
        // temp trend
        let trendText = 'Normal', trendClass = 'bg-green-100 text-green-700';
        if(currentTemp >= 28) { trendText = 'Critical'; trendClass = 'bg-red-100 text-red-700'; }
        else if(currentTemp >= 24) { trendText = 'Warming'; trendClass = 'bg-orange-100 text-orange-700'; }
        else if(currentTemp <= 18) { trendText = 'Cool'; trendClass = 'bg-blue-100 text-blue-700'; }
        document.getElementById('trendLabel').innerHTML = trendText;
        document.getElementById('trendLabel').className = `ml-2 text-xs font-bold px-2 py-0.5 rounded-full ${trendClass}`;
        document.getElementById('tempMainIcon').innerHTML = currentTemp >= 28 ? '🔥' : (currentTemp >= 24 ? '⚠️' : '🌡️');
        let sigClass = 'bg-green-100 text-green-700', sigLabel = 'Excellent';
        if(currentSignal < 30) { sigLabel = 'Critical'; sigClass = 'bg-red-100 text-red-700'; }
        else if(currentSignal < 60) { sigLabel = 'Fair'; sigClass = 'bg-yellow-100 text-yellow-700'; }
        document.getElementById('signalQuality').innerHTML = sigLabel;
        document.getElementById('signalQuality').className = `ml-2 text-xs font-bold px-2 py-0.5 rounded-full ${sigClass}`;
        document.getElementById('signalIconMain').innerHTML = currentSignal < 40 ? '⚠️' : '📶';
        // AI anomaly detection
        let anomalyMsg = 'None', anomalyColor = 'text-gray-600';
        if(currentTemp >= 28 || currentSignal < 30) { anomalyMsg = 'Critical'; anomalyColor = 'text-red-600'; }
        else if(currentTemp >= 24 || currentSignal < 55) { anomalyMsg = 'Warning'; anomalyColor = 'text-orange-600'; }
        document.getElementById('anomalyDetect').innerHTML = anomalyMsg;
        document.getElementById('anomalyDetect').className = `text-xl font-bold ${anomalyColor}`;
        // AI score
        let score = 100;
        if(currentTemp >= 28) score -= 40; else if(currentTemp >= 24) score -= 20;
        if(currentSignal < 30) score -= 40; else if(currentSignal < 60) score -= 20;
        score = Math.max(0, Math.min(100, score));
        document.getElementById('aiScoreVal').innerHTML = score + '%';
        let health = 'Excellent'; let healthColor='text-emerald-600';
        if(score<50) { health='Critical'; healthColor='text-red-600'; }
        else if(score<75) { health='Warning'; healthColor='text-amber-600'; }
        document.getElementById('aiHealthVal').innerHTML = health;
        document.getElementById('aiHealthVal').className = `text-2xl font-bold ${healthColor}`;
        document.getElementById('aiInsights').innerHTML = `<p>• Temp ${currentTemp.toFixed(1)}°C | Signal ${currentSignal}%</p><p>• Anomaly level: ${anomalyMsg}</p>`;
        let recMsg = 'System stable.';
        if(currentTemp>=24) recMsg = 'Reduce environment temperature.';
        if(currentSignal<50) recMsg = 'Check WiFi / router placement.';
        document.getElementById('aiRecommendations').innerHTML = `<p>🔹 ${recMsg}</p>`;
        document.getElementById('aiPredictions').innerHTML = `<p>Next 24h: temperature expected within ±1.2°C range.</p>`;
        document.getElementById('dataPointsCount').innerHTML = temperatureHistoryArray.length;
    }
    
    // sparklines (mini temp & signal)
    let sparkTempChart, sparkSignalChart;
    function initSparklines() {
        const ctxTemp = document.getElementById('sparkTempChart').getContext('2d');
        const ctxSig = document.getElementById('sparkSignalChart').getContext('2d');
        sparkTempChart = new Chart(ctxTemp, { type:'line', data:{labels:[], datasets:[{data:[], borderColor:'#f97316', borderWidth:2, fill:false, tension:0.3, pointRadius:0}]}, options:{responsive:true, maintainAspectRatio:true, plugins:{legend:{display:false}, tooltip:{enabled:false}}, scales:{x:{display:false}, y:{display:false}} } });
        sparkSignalChart = new Chart(ctxSig, { type:'line', data:{labels:[], datasets:[{data:[], borderColor:'#3b82f6', borderWidth:2, fill:false, tension:0.3, pointRadius:0}]}, options:{responsive:true, maintainAspectRatio:true, plugins:{legend:{display:false}, tooltip:{enabled:false}}, scales:{x:{display:false}, y:{display:false}} } });
    }
    function updateMiniCharts() {
        let tempVals = temperatureHistoryArray.slice(-20).map(p=>p.temp);
        let sigVals = signalHistoryArray.slice(-20).map(p=>p.signal);
        sparkTempChart.data.datasets[0].data = tempVals;
        sparkSignalChart.data.datasets[0].data = sigVals;
        sparkTempChart.update('none');
        sparkSignalChart.update('none');
    }
    
    // main history chart (temperature + signal dual axis)
    let mainChart;
    function initMainChart() {
        const ctx = document.getElementById('mainHistoryChart').getContext('2d');
        mainChart = new Chart(ctx, { type:'line', data:{labels:[], datasets:[{label:'Temperature °C', data:[], borderColor:'#f59e0b', backgroundColor:'rgba(245,158,11,0.05)', tension:0.3, fill:true, yAxisID:'y'},{label:'Signal %', data:[], borderColor:'#3b82f6', backgroundColor:'rgba(59,130,246,0.05)', tension:0.3, fill:true, yAxisID:'y1'}]}, options:{responsive:true, maintainAspectRatio:true, interaction:{mode:'index', intersect:false}, plugins:{zoom:{zoom:{wheel:{enabled:true}, mode:'x'}}}, scales:{y:{title:{display:true, text:'Temp (°C)'}}, y1:{position:'right', title:{text:'Signal (%)'}, grid:{drawOnChartArea:false}}}} });
    }
    function refreshHistoryByPeriod() {
        let now = Date.now();
        let boundary = now;
        if(historyPeriod === '24h') boundary = now - 24*3600*1000;
        else if(historyPeriod === '7d') boundary = now - 7*24*3600*1000;
        else boundary = now - 30*24*3600*1000;
        let filteredTemp = temperatureHistoryArray.filter(p=>p.timestamp >= boundary);
        let filteredSig = signalHistoryArray.filter(p=>p.timestamp >= boundary);
        if(filteredTemp.length === 0 && temperatureHistoryArray.length) { filteredTemp = [temperatureHistoryArray[temperatureHistoryArray.length-1]]; filteredSig = [signalHistoryArray[signalHistoryArray.length-1]]; }
        let labels = filteredTemp.map(p=> {
            let d = new Date(p.timestamp);
            if(historyPeriod === '24h') return d.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
            else return d.toLocaleDateString([], {month:'short', day:'numeric'});
        });
        let tempData = filteredTemp.map(p=>p.temp);
        let sigData = filteredSig.map(p=>p.signal);
        mainChart.data.labels = labels;
        mainChart.data.datasets[0].data = tempData;
        mainChart.data.datasets[1].data = sigData;
        mainChart.update();
        if(tempData.length) {
            let minT = Math.min(...tempData); let maxT = Math.max(...tempData); let avgT = tempData.reduce((a,b)=>a+b,0)/tempData.length;
            document.getElementById('histMin').innerHTML = minT.toFixed(1)+'°C';
            document.getElementById('histMax').innerHTML = maxT.toFixed(1)+'°C';
            document.getElementById('histAvg').innerHTML = avgT.toFixed(1)+'°C';
        }
        // 24h prediction based on recent
        if(tempData.length > 5) {
            let recent = tempData.slice(-12);
            let avg = recent.reduce((a,b)=>a+b,0)/recent.length;
            let trend = (recent[recent.length-1] - recent[0]) / Math.max(1, recent.length);
            let predLabels = [], predVals = [];
            for(let i=1;i<=24;i++) { predLabels.push(`+${i}h`); predVals.push(parseFloat((avg + trend*i + (Math.random()*0.4-0.2)).toFixed(1))); }
            if(charts.predChart) { charts.predChart.data.labels = predLabels; charts.predChart.data.datasets[0].data = predVals; charts.predChart.update(); }
        }
    }
    
    // Alert Curves (simulated data per last 7 days)
    let alertChart;
    function updateAlertCurvesData() {
        let days = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
        let tempAlerts = [], sigAlerts = [], connAlerts = [];
        for(let i=0;i<7;i++) {
            let baseTemp = (currentTemp > 26 ? Math.floor(Math.random()*5)+2 : Math.floor(Math.random()*3));
            let baseSig = (currentSignal < 50 ? Math.floor(Math.random()*6)+1 : Math.floor(Math.random()*2));
            tempAlerts.push(baseTemp);
            sigAlerts.push(baseSig);
            connAlerts.push(Math.floor(Math.random()*2));
        }
        if(alertChart) {
            alertChart.data.labels = days;
            alertChart.data.datasets[0].data = tempAlerts;
            alertChart.data.datasets[1].data = sigAlerts;
            alertChart.data.datasets[2].data = connAlerts;
            alertChart.update();
        }
        let totalTemp = tempAlerts.reduce((a,b)=>a+b,0);
        let totalSig = sigAlerts.reduce((a,b)=>a+b,0);
        let totalConn = connAlerts.reduce((a,b)=>a+b,0);
        document.getElementById('totalTempAlerts').innerHTML = totalTemp;
        document.getElementById('totalSignalAlerts').innerHTML = totalSig;
        document.getElementById('totalConnAlerts').innerHTML = totalConn;
        document.getElementById('globalAlertsSum').innerHTML = totalTemp+totalSig+totalConn;
    }
    
    function initAlertCurveChart() {
        const ctx = document.getElementById('tripleAlertChart').getContext('2d');
        alertChart = new Chart(ctx, { type:'line', data:{ labels:[], datasets:[{label:'🌡️ Temp alerts', data:[], borderColor:'#EF4444', backgroundColor:'rgba(239,68,68,0.05)', tension:0.3, fill:true, borderWidth:2},{label:'📶 Signal alerts', data:[], borderColor:'#F59E0B', backgroundColor:'rgba(245,158,11,0.05)', tension:0.3, fill:true},{label:'🔌 Connection alerts', data:[], borderColor:'#3B82F6', backgroundColor:'rgba(59,130,246,0.05)', tension:0.3, fill:true}]}, options:{responsive:true, maintainAspectRatio:true, plugins:{legend:{position:'top'}}}});
        updateAlertCurvesData();
    }
    
    // Prediction Chart init
    function initPredictionChart() {
        const ctx = document.getElementById('futurePredictionCanvas').getContext('2d');
        charts.predChart = new Chart(ctx, { type:'line', data:{ labels:[], datasets:[{label:'Forecast °C', data:[], borderColor:'#8b5cf6', borderDash:[6,6], backgroundColor:'rgba(139,92,246,0.05)', tension:0.3, fill:true, borderWidth:2}]}, options:{responsive:true, maintainAspectRatio:true}});
    }
    
    function setHistoryPeriod(period) {
        historyPeriod = period;
        ['24h','7d','30d'].forEach(p => { let btn = document.getElementById(`btn${p}`); if(btn) btn.className = `text-xs ${p===period?'bg-blue-600 text-white':'bg-gray-200 text-gray-700'} px-3 py-1 rounded-md`; });
        refreshHistoryByPeriod();
    }
    
    function updateAIPredictionsAndRecommendations() {
        // dynamic update on each cycle
        let insightDiv = document.getElementById('aiInsights');
        insightDiv.innerHTML = `<p>• Live: ${currentTemp.toFixed(1)}°C / ${currentSignal}%</p><p>• ${currentTemp>=25 ? 'Heating trend' : 'Stable thermal'}</p>`;
        let predText = `Next 24h: expected peak ~${(currentTemp+0.8).toFixed(1)}°C.`;
        document.getElementById('aiPredictions').innerHTML = `<p>🔮 ${predText}</p>`;
    }
    
    // PDF generation using captured chart images
    async function generateFullReport() {
        let loader = document.getElementById('pdfLoading');
        loader.style.display = 'block';
        try {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('p','mm','a4');
            doc.setFontSize(22);
            doc.text('Environet Analytics Report', 20, 25);
            doc.setFontSize(11);
            doc.text(`Generated: ${new Date().toLocaleString()}`, 20, 38);
            doc.text(`Temperature: ${currentTemp.toFixed(1)}°C | Signal: ${currentSignal}%`, 20, 50);
            doc.text(`AI Health Score: ${document.getElementById('aiScoreVal').innerText}`, 20, 60);
            // capture history chart
            let histCanvas = document.getElementById('mainHistoryChart');
            if(histCanvas) { let imgData = histCanvas.toDataURL('image/png'); doc.addImage(imgData, 'PNG', 15, 75, 180, 70); }
            // capture alert chart
            let alertCanvas = document.getElementById('tripleAlertChart');
            if(alertCanvas) { let img2 = alertCanvas.toDataURL('image/png'); doc.addPage(); doc.text('Alert Trends', 20, 20); doc.addImage(img2, 'PNG', 15, 35, 180, 70); }
            doc.save(`environet_report_${Date.now()}.pdf`);
        } catch(err) { console.error(err); alert('PDF error'); }
        finally { loader.style.display = 'none'; }
    }
    
    // periodic refresh
    function startSimulation() {
        generateNewReadings();
        setInterval(() => { generateNewReadings(); }, 28000);
    }
    
    window.setHistoryPeriod = setHistoryPeriod;
    window.generateFullReport = generateFullReport;
    
    document.addEventListener('DOMContentLoaded', () => {
        initSparklines();
        initMainChart();
        initAlertCurveChart();
        initPredictionChart();
        for(let i=0;i<30;i++) { // seed history
            let t = 22 + Math.sin(i)*2 + Math.random()*1.5;
            let s = 75 + Math.random()*20;
            temperatureHistoryArray.push({timestamp: Date.now() - (30-i)*3600000, temp: parseFloat(t.toFixed(1))});
            signalHistoryArray.push({timestamp: Date.now() - (30-i)*3600000, signal: Math.floor(s)});
        }
        currentTemp = temperatureHistoryArray[temperatureHistoryArray.length-1].temp;
        currentSignal = signalHistoryArray[signalHistoryArray.length-1].signal;
        updateUIFromCurrent();
        updateMiniCharts();
        refreshHistoryByPeriod();
        updateAlertCurvesData();
        startSimulation();
    });
</script>
</body>
</html>
