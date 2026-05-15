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
