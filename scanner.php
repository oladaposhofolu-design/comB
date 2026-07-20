<?php include 'db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>MIST Attendance System</title>
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#003366">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="MIST Attend">
<script>
if('serviceWorker' in navigator){
    navigator.serviceWorker.register('/sw.js');
}
</script>
<script src="https://unpkg.com/html5-qrcode"></script>
<style>
*{ margin:0; padding:0; box-sizing:border-box; font-family:Arial,Helvetica,sans-serif; }
body{ background:#f2f5f9; }
header{
    background:#003366; color:white;
    padding:16px 20px;
    display:flex; align-items:center; justify-content:space-between;
}
header .header-left h1{ font-size:17px; }
header .header-left h3{ font-size:13px; font-weight:400; opacity:0.85; margin-top:3px; }
header .header-right a{
    color:white; text-decoration:none;
    background:rgba(255,255,255,0.15);
    padding:8px 14px; border-radius:6px;
    font-size:13px; margin-left:8px; transition:background 0.2s;
}
header .header-right a:hover{ background:rgba(255,255,255,0.28); }
.container{ width:95%; margin:auto; margin-top:20px; }
.top-box{ display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px; }
.card{ background:white; padding:20px; border-radius:10px; box-shadow:0 0 10px rgba(0,0,0,0.08); }
.card h2{ margin-bottom:15px; color:#003366; }
#reader{ width:100%; }
input,select{
    width:100%; padding:10px 12px; margin-top:10px;
    border-radius:5px; border:1px solid #ccc; font-size:14px;
}
button{
    width:100%; padding:12px; margin-top:10px;
    border-radius:5px; border:none;
    background:#003366; color:white;
    cursor:pointer; font-weight:bold; font-size:14px; transition:background 0.2s;
}
button:hover{ background:#00509e; }
.today-box{
    margin-top:20px; background:#f0f4ff;
    border-left:4px solid #003366;
    padding:12px 16px; border-radius:6px;
}
.today-box h3{ color:#003366; margin-bottom:4px; font-size:13px; text-transform:uppercase; letter-spacing:1px; }
.today-box .day-name{ font-size:22px; font-weight:bold; color:#00509e; }
.today-box .full-date{ font-size:13px; color:#666; margin-top:2px; }
#scan-status{ margin-top:10px; font-size:13px; color:#555; min-height:20px; }
.cooldown-bar-wrap{ height:6px; background:#e0e0e0; border-radius:3px; margin-top:5px; display:none; }
.cooldown-bar{ height:6px; background:#003366; border-radius:3px; transition:width 0.1s linear; }
.success{ background:#d4edda; color:#155724; padding:10px; margin-top:10px; border-radius:5px; }
.error{ background:#f8d7da; color:#721c24; padding:10px; margin-top:10px; border-radius:5px; }

/* Filter */
.date-filter{ display:flex; gap:10px; align-items:flex-end; margin-bottom:16px; flex-wrap:wrap; }
.filter-group{ display:flex; flex-direction:column; gap:4px; flex:1; min-width:120px; }
.filter-group label{ font-size:12px; font-weight:700; color:#003366; }
.filter-group select,
.filter-group input[type="date"]{ width:100%; padding:8px 12px; margin-top:0; border-radius:6px; border:1.5px solid #ddd; font-size:13px; }
.filter-btns{ display:flex; gap:8px; align-items:flex-end; }
.filter-btns button{ width:auto; padding:8px 16px; margin-top:0; font-size:13px; }
.filter-mode{ display:flex; gap:8px; margin-bottom:14px; flex-wrap:wrap; }
.mode-btn{
    padding:7px 16px; border-radius:20px; border:2px solid #003366;
    background:white; color:#003366; font-size:13px; font-weight:600;
    cursor:pointer; transition:all 0.2s; width:auto; margin-top:0;
}
.mode-btn.active{ background:#003366; color:white; }

table{ width:100%; border-collapse:collapse; background:white; margin-top:10px; }
table th{ background:#003366; color:white; padding:11px 10px; font-size:13px; }
table td{ padding:9px 10px; border:1px solid #eee; text-align:center; font-size:13px; }
table tr:nth-child(even) td{ background:#f8f9fa; }
.allowed{ color:green; font-weight:bold; }
.denied{ color:red; font-weight:bold; }
.record-count{ font-size:13px; color:#666; margin-bottom:10px; }

/* ── PIN MODAL ── */
.pin-overlay{
    display:none; position:fixed; inset:0;
    background:rgba(0,0,0,0.6); z-index:999;
    align-items:center; justify-content:center;
}
.pin-overlay.show{ display:flex; }
.pin-box{
    background:white; border-radius:16px; padding:36px 32px;
    text-align:center; width:100%; max-width:360px;
    box-shadow:0 20px 60px rgba(0,0,0,0.3);
    animation: popIn 0.25s ease;
}
@keyframes popIn{
    from{ transform:scale(0.85); opacity:0; }
    to{   transform:scale(1);    opacity:1; }
}
.pin-box .pin-icon{
    width:64px; height:64px; border-radius:50%;
    background:linear-gradient(135deg,#003366,#00509e);
    display:flex; align-items:center; justify-content:center;
    margin:0 auto 14px;
    font-size:26px; color:white;
}
.pin-box h3{ color:#003366; font-size:18px; margin-bottom:4px; }
.pin-box .pin-student-name{
    font-size:14px; font-weight:700; color:#00509e;
    margin-bottom:4px;
}
.pin-box p{ color:#888; font-size:13px; margin-bottom:20px; }

/* PIN dots display */
.pin-dots{
    display:flex; justify-content:center; gap:12px; margin-bottom:20px;
}
.pin-dot{
    width:16px; height:16px; border-radius:50%;
    border:2px solid #003366; background:white;
    transition:background 0.15s;
}
.pin-dot.filled{ background:#003366; }

/* PIN keypad */
.pin-keypad{
    display:grid; grid-template-columns:repeat(3,1fr);
    gap:10px; margin-bottom:16px;
}
.pin-key{
    padding:14px; border-radius:10px; border:1.5px solid #ddd;
    background:white; font-size:18px; font-weight:700; color:#003366;
    cursor:pointer; transition:all 0.15s; width:auto; margin-top:0;
}
.pin-key:hover{ background:#f0f4ff; border-color:#003366; }
.pin-key:active{ background:#003366; color:white; }
.pin-key.del{ background:#f8f9fa; font-size:14px; color:#666; }
.pin-key.del:hover{ background:#f8d7da; color:#721c24; border-color:#dc3545; }
.pin-key.zero{ grid-column:2; }

.pin-error{
    background:#f8d7da; color:#721c24;
    padding:8px 12px; border-radius:6px;
    font-size:13px; margin-bottom:12px; display:none;
}
.pin-cancel{
    background:transparent; color:#666; border:1.5px solid #ddd;
    padding:10px; border-radius:8px; font-size:13px; cursor:pointer;
    width:100%; margin-top:0; transition:all 0.2s;
}
.pin-cancel:hover{ background:#f8f9fa; }

/* Shake animation for wrong PIN */
@keyframes shake{
    0%,100%{ transform:translateX(0); }
    20%{ transform:translateX(-8px); }
    40%{ transform:translateX(8px); }
    60%{ transform:translateX(-6px); }
    80%{ transform:translateX(6px); }
}
.pin-box.shake{ animation:shake 0.4s ease; }

@media(max-width:900px){ .top-box{ grid-template-columns:1fr; } }
@media (max-width: 768px) {
  .layout {
    flex-direction: column;
  }
  .sidebar {
    position: relative;
    width: 100%;
    height: auto;
    top: 0;
    left: 0;
  }
  .main {
    width: 100%;
    margin-left: 0;
  }
  table {
    display: block;
    overflow-x: auto;
    white-space: nowrap;
  }
}

@media (max-width: 480px) {
  h2 {
    font-size: 1.2rem;
  }
  .btn {
    width: 100%;
    margin-bottom: 8px;
  }
}

</style>
</head>
<body>

<?php
$sAll = mysqli_query($conn, "SELECT * FROM students ORDER BY student_code ASC");
$sArr = [];
while($s = mysqli_fetch_assoc($sAll)) $sArr[] = $s;
?>

<header>
    <div class="header-left">
        <h1>MINISTRY OF INNOVATION SCIENCE AND TECHNOLOGY</h1>
        <h3>SIWES Attendance Management System</h3>
    </div>
    <div class="header-right">
        <a href="index.php"><i class="fa fa-home"></i> Home</a>
        <a href="#" onclick="openSecretKey(event)"><i class="fa fa-lock"></i> Admin</a>
    </div>
</header>

<div class="container">
<div class="top-box">

<!-- QR SCANNER -->
<div class="card">
    <h2><i class="fa fa-qrcode"></i> QR Code Scanner</h2>
    <div id="reader"></div>
    <div id="scan-status"></div>
    <div class="cooldown-bar-wrap" id="cooldownWrap">
        <div class="cooldown-bar" id="cooldownBar" style="width:100%"></div>
    </div>
    <div id="message"></div>
</div>

<!-- MANUAL ATTENDANCE -->
<div class="card">
    <h2><i class="fa fa-user-check"></i> Manual Attendance</h2>
    <select id="studentSelect">
        <option value="">-- Select Student --</option>
        <?php foreach($sArr as $s): ?>
        <option value="<?= htmlspecialchars($s['student_code']) ?>">
            <?= htmlspecialchars($s['full_name']) ?> (<?= htmlspecialchars($s['student_code']) ?>)
        </option>
        <?php endforeach; ?>
    </select>
    <button onclick="markAttendanceManual()">
        <i class="fa fa-fingerprint"></i> Mark Attendance
    </button>
    <div class="today-box">
        <h3>Today</h3>
        <div class="day-name" id="todayDay"></div>
        <div class="full-date" id="fullDate"></div>
    </div>
</div>

</div>

<!-- ATTENDANCE RECORDS -->
<div class="card">
    <h2><i class="fa fa-list"></i> Attendance Records</h2>

    <div class="filter-mode">
        <button class="mode-btn active" id="btnToday" onclick="setMode('today')">
            <i class="fa fa-calendar-day"></i> Today
        </button>
        <button class="mode-btn" id="btnDate" onclick="setMode('date')">
            <i class="fa fa-calendar"></i> Specific Date
        </button>
        <button class="mode-btn" id="btnMonth" onclick="setMode('month')">
            <i class="fa fa-calendar-alt"></i> Month/Year
        </button>
    </div>

    <div id="filterToday" class="date-filter">
        <div class="filter-group"><label>Showing records for today only</label></div>
        <div class="filter-btns">
            <button onclick="loadRecords()" style="background:#003366;">
                <i class="fa fa-rotate"></i> Refresh
            </button>
        </div>
    </div>

    <div id="filterDate" class="date-filter" style="display:none">
        <div class="filter-group">
            <label><i class="fa fa-calendar-day"></i> Select Date</label>
            <input type="date" id="specificDate"/>
        </div>
        <div class="filter-btns">
            <button onclick="loadRecords()" style="background:#003366;">
                <i class="fa fa-filter"></i> Apply
            </button>
            <button onclick="setMode('today')" style="background:#6c757d;">
                <i class="fa fa-times"></i> Clear
            </button>
        </div>
    </div>

    <div id="filterMonth" class="date-filter" style="display:none">
        <div class="filter-group">
            <label>Year</label>
            <select id="filterYear"></select>
        </div>
        <div class="filter-group">
            <label>Month</label>
            <select id="filterMonthSel"></select>
        </div>
        <div class="filter-btns">
            <button onclick="loadRecords()" style="background:#003366;">
                <i class="fa fa-filter"></i> Apply
            </button>
            <button onclick="setMode('today')" style="background:#6c757d;">
                <i class="fa fa-times"></i> Clear
            </button>
        </div>
    </div>

    <div class="record-count" id="recordCount"></div>
    <table>
        <thead>
            <tr>
                <th>#</th><th>Student ID</th><th>Name</th>
                <th>Allowed Days</th><th>Date</th><th>Day</th>
                <th>Status</th><th>Time</th>
            </tr>
        </thead>
        <tbody id="attendanceBody"></tbody>
    </table>
    <p id="noRecords" style="text-align:center;padding:20px;color:#999;display:none;">
        No records found for the selected period.
    </p>
</div>

</div><!-- end container -->

<!-- ── PIN MODAL ── -->
<div class="pin-overlay" id="pinOverlay">
    <div class="pin-box" id="pinBox">
        <div class="pin-icon"><i class="fa fa-lock"></i></div>
        <h3>PIN Verification</h3>
        <div class="pin-student-name" id="pinStudentName"></div>
        <p>Enter your 4-digit PIN to mark attendance</p>

        <!-- PIN dots -->
        <div class="pin-dots">
            <div class="pin-dot" id="dot0"></div>
            <div class="pin-dot" id="dot1"></div>
            <div class="pin-dot" id="dot2"></div>
            <div class="pin-dot" id="dot3"></div>
        </div>

        <!-- Error message -->
        <div class="pin-error" id="pinError"></div>

        <!-- Keypad -->
        <div class="pin-keypad">
            <button class="pin-key" onclick="pinPress('1')">1</button>
            <button class="pin-key" onclick="pinPress('2')">2</button>
            <button class="pin-key" onclick="pinPress('3')">3</button>
            <button class="pin-key" onclick="pinPress('4')">4</button>
            <button class="pin-key" onclick="pinPress('5')">5</button>
            <button class="pin-key" onclick="pinPress('6')">6</button>
            <button class="pin-key" onclick="pinPress('7')">7</button>
            <button class="pin-key" onclick="pinPress('8')">8</button>
            <button class="pin-key" onclick="pinPress('9')">9</button>
            <button class="pin-key del" onclick="pinDelete()"><i class="fa fa-delete-left"></i></button>
            <button class="pin-key zero" onclick="pinPress('0')">0</button>
            <button class="pin-key" style="background:#003366;color:white;" onclick="pinSubmit()">
                <i class="fa fa-check"></i>
            </button>
        </div>

        <button class="pin-cancel" onclick="closePin()">
            <i class="fa fa-times"></i> Cancel
        </button>
    </div>
</div>

<script>
const studentsFromDB = <?= json_encode($sArr) ?>;
const students = studentsFromDB.map(s => ({
    id:   s.student_code,
    name: s.full_name,
    days: s.allowed_days.split(',')
}));

// Date display
const nowDate     = new Date();
const today       = nowDate.toLocaleDateString('en-US',{weekday:'long'});
const fullDateStr = nowDate.toLocaleDateString('en-US',{year:'numeric',month:'long',day:'numeric'});
const todayISO    = nowDate.toISOString().split('T')[0];
document.getElementById("todayDay").textContent = today;
document.getElementById("fullDate").textContent = fullDateStr;
document.getElementById("specificDate").value = todayISO;
document.getElementById("specificDate").max   = todayISO;

// Calendar filter
const monthNames = ["January","February","March","April","May","June",
                    "July","August","September","October","November","December"];
const filterYear    = document.getElementById("filterYear");
const filterMonthSel= document.getElementById("filterMonthSel");
for(let y=nowDate.getFullYear();y>=nowDate.getFullYear()-4;y--){
    const opt=document.createElement("option");
    opt.value=y;opt.textContent=y;
    if(y===nowDate.getFullYear())opt.selected=true;
    filterYear.appendChild(opt);
}
monthNames.forEach((m,i)=>{
    const opt=document.createElement("option");
    opt.value=i+1;opt.textContent=m;
    if(i===nowDate.getMonth())opt.selected=true;
    filterMonthSel.appendChild(opt);
});

let currentMode='today';
function setMode(mode){
    currentMode=mode;
    document.getElementById('filterToday').style.display=mode==='today'?'flex':'none';
    document.getElementById('filterDate').style.display =mode==='date' ?'flex':'none';
    document.getElementById('filterMonth').style.display=mode==='month'?'flex':'none';
    document.getElementById('btnToday').classList.toggle('active',mode==='today');
    document.getElementById('btnDate').classList.toggle('active', mode==='date');
    document.getElementById('btnMonth').classList.toggle('active',mode==='month');
    loadRecords();
}

function loadRecords(){
    let url='get_records.php';
    if(currentMode==='today')      url+='?date='+todayISO;
    else if(currentMode==='date'){
        const d=document.getElementById('specificDate').value;
        url+='?date='+(d||todayISO);
    } else {
        url+='?year='+filterYear.value+'&month='+filterMonthSel.value;
    }
    fetch(url).then(r=>r.json()).then(data=>{
        const body=document.getElementById("attendanceBody");
        body.innerHTML="";
        document.getElementById("noRecords").style.display=data.length===0?"block":"none";
        document.getElementById("recordCount").textContent=data.length>0?'Showing '+data.length+' record(s)':"";
        data.forEach((r,i)=>{
            body.innerHTML+='<tr>'
                +'<td>'+(i+1)+'</td><td>'+r.student_code+'</td>'
                +'<td>'+(r.full_name||'')+'</td>'
                +'<td class="allowed">'+(r.allowed_days||'')+'</td>'
                +'<td>'+r.attendance_date+'</td><td>'+r.attendance_day+'</td>'
                +'<td class="'+(r.status==='ALLOWED'?'allowed':'denied')+'">'+r.status+'</td>'
                +'<td>'+r.attendance_time+'</td></tr>';
        });
    });
}
loadRecords();

// =============================================
// PIN MODAL
// =============================================
let pinValue       = '';
let pinStudentId   = '';
let pinAttempts    = 0;
const MAX_ATTEMPTS = 3;

function openPin(studentId, studentName){
    pinValue     = '';
    pinAttempts  = 0;
    pinStudentId = studentId;
    document.getElementById('pinStudentName').textContent = studentName;
    document.getElementById('pinError').style.display = 'none';
    document.getElementById('pinError').textContent   = '';
    updateDots();
    document.getElementById('pinOverlay').classList.add('show');
}

function closePin(){
    document.getElementById('pinOverlay').classList.remove('show');
    pinValue = '';
    updateDots();
}

function pinPress(digit){
    if(pinValue.length >= 4) return;
    pinValue += digit;
    updateDots();
    // Auto-submit when 4 digits entered
    if(pinValue.length === 4){
        setTimeout(pinSubmit, 200);
    }
}

function pinDelete(){
    pinValue = pinValue.slice(0,-1);
    updateDots();
    document.getElementById('pinError').style.display='none';
}

function updateDots(){
    for(let i=0;i<4;i++){
        const dot=document.getElementById('dot'+i);
        dot.classList.toggle('filled', i < pinValue.length);
    }
}

function pinSubmit(){
    if(pinValue.length === 0){
        showPinError('Please enter your PIN.');
        return;
    }
    if(pinValue.length < 4){
        showPinError('PIN must be 4 digits.');
        return;
    }

    // Verify PIN with server
    fetch('verify_pin.php',{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({ student_code: pinStudentId, pin: pinValue })
    })
    .then(r=>r.json())
    .then(data=>{
        if(data.success){
            closePin();
            // PIN correct — now mark attendance
            checkAttendance(pinStudentId, 'manual');
        } else {
            pinAttempts++;
            pinValue = '';
            updateDots();
            // Shake animation
            const box = document.getElementById('pinBox');
            box.classList.remove('shake');
            void box.offsetWidth; // reflow
            box.classList.add('shake');
            setTimeout(()=>box.classList.remove('shake'), 400);

            if(pinAttempts >= MAX_ATTEMPTS){
                showPinError('Too many failed attempts. Please try again later.');
                setTimeout(closePin, 2000);
            } else {
                showPinError(data.message + ' (' + (MAX_ATTEMPTS - pinAttempts) + ' attempt(s) left)');
            }
        }
    });
}

function showPinError(msg){
    const err = document.getElementById('pinError');
    err.textContent = msg;
    err.style.display = 'block';
}

// Close modal on overlay click
document.getElementById('pinOverlay').addEventListener('click', function(e){
    if(e.target === this) closePin();
});

// Keyboard support for PIN
document.addEventListener('keydown', function(e){
    if(!document.getElementById('pinOverlay').classList.contains('show')) return;
    if(e.key >= '0' && e.key <= '9') pinPress(e.key);
    else if(e.key === 'Backspace') pinDelete();
    else if(e.key === 'Enter') pinSubmit();
    else if(e.key === 'Escape') closePin();
});

// =============================================
// COOLDOWN (QR only)
// =============================================
const lastScanTime = {};
const COOLDOWN_MS  = 10000;
let cooldownInterval = null;

function startCooldownBar(ms){
    const wrap=document.getElementById("cooldownWrap");
    const bar =document.getElementById("cooldownBar");
    wrap.style.display="block"; bar.style.width="100%";
    const start=Date.now();
    clearInterval(cooldownInterval);
    cooldownInterval=setInterval(()=>{
        const pct=Math.max(0,100-((Date.now()-start)/ms*100));
        bar.style.width=pct+"%";
        if(pct<=0){
            clearInterval(cooldownInterval);
            wrap.style.display="none";
            document.getElementById("scan-status").textContent="✅ Ready to scan.";
        }
    },100);
}

function saveAttendance(student, status, dayName, source){
    fetch('mark_attendance.php',{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({
            student_code:student.id, full_name:student.name,
            allowed_days:student.days.join(','), status:status,
            day:dayName, source:source
        })
    })
    .then(r=>r.json())
    .then(data=>{
        if(data.duplicate){
            // Student already marked attendance today
            showMessage(data.message, 'error');
            // Reset cooldown lock so they aren't stuck if they retry
            delete lastScanTime[student.id];
            document.getElementById("scan-status").textContent = "";
            document.getElementById("cooldownWrap").style.display = "none";
            clearInterval(cooldownInterval);
        } else {
            loadRecords();
        }
    });
}

function checkAttendance(studentId, source){
    const student=students.find(s=>s.id===studentId);
    if(!student){ showMessage('Unknown ID: '+studentId,'error'); return; }

    if(source==="qr"){
        const last=lastScanTime[studentId];
        if(last&&(Date.now()-last)<COOLDOWN_MS){
            const rem=Math.ceil((COOLDOWN_MS-(Date.now()-last))/1000);
            document.getElementById("scan-status").textContent='⏳ Already scanned! Wait '+rem+'s.';
            return;
        }
        lastScanTime[studentId]=Date.now();
        document.getElementById("scan-status").textContent='✅ Scanned: '+student.name;
        startCooldownBar(COOLDOWN_MS);
    }

    const now     = new Date();
    const dayName = now.toLocaleDateString('en-US',{weekday:'long'});
    const status  = student.days.includes(dayName)?"ALLOWED":"DENIED";
    saveAttendance(student, status, dayName, source);
    showMessage(status==="ALLOWED"
        ? student.name+' — attendance accepted ✓'
        : student.name+' is NOT allowed today ('+dayName+').',
        status==="ALLOWED"?"success":"error");
}

// Manual — opens PIN modal first
function markAttendanceManual(){
    const sel=document.getElementById("studentSelect");
    if(!sel.value){ showMessage('Please select a student.','error'); return; }
    const student=students.find(s=>s.id===sel.value);
    if(!student){ showMessage('Student not found.','error'); return; }
    openPin(student.id, student.name);
}

function showMessage(text,type){
    const msg=document.getElementById("message");
    msg.innerHTML='<div class="'+type+'">'+text+'</div>';
    setTimeout(()=>{msg.innerHTML="";},4000);
}

// QR Scanner
const html5QrCode=new Html5QrcodeScanner("reader",{fps:10,qrbox:250});
html5QrCode.render(decodedText=>checkAttendance(decodedText.trim(),"qr"));
</script>

<!-- SECRET KEY MODAL -->
<div class="sk-overlay" id="skOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.65);z-index:9999;align-items:center;justify-content:center;">
    <div class="sk-box" id="skBox" style="background:white;border-radius:18px;padding:40px 36px;text-align:center;max-width:380px;width:92%;box-shadow:0 24px 60px rgba(0,0,0,0.4);">
        <div style="width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,#003366,#00509e);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:26px;color:white;">
            <i class="fa fa-shield-halved"></i>
        </div>
        <h3 style="color:#003366;font-size:20px;margin-bottom:6px;">Admin Access</h3>
        <p style="color:#888;font-size:13px;margin-bottom:22px;">Enter the secret key to proceed to the admin login page.</p>

        <div style="position:relative;margin-bottom:12px;">
            <input type="password" id="skInput" maxlength="20" placeholder="Enter secret key"
                style="width:100%;padding:14px 44px 14px 16px;border:2px solid #ddd;border-radius:10px;font-size:15px;letter-spacing:3px;text-align:center;outline:none;"
                onkeydown="if(event.key==='Enter') verifyKey()"
                onfocus="this.style.borderColor='#003366'"
                onblur="this.style.borderColor='#ddd'"/>
            <button onclick="toggleSKShow()" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#999;font-size:16px;padding:0;width:auto;margin:0;">
                <i class="fa fa-eye" id="skEyeIcon"></i>
            </button>
        </div>

        <div id="skError" style="background:#f8d7da;color:#721c24;padding:9px 14px;border-radius:8px;font-size:13px;margin-bottom:10px;display:none;"></div>
        <div id="skAttempts" style="font-size:12px;color:#999;margin-bottom:12px;"></div>

        <button onclick="verifyKey()"
            style="width:100%;padding:14px;border:none;border-radius:10px;background:linear-gradient(135deg,#003366,#00509e);color:white;font-size:15px;font-weight:700;cursor:pointer;margin-bottom:10px;">
            <i class="fa fa-arrow-right"></i>&nbsp; Proceed
        </button>
        <button onclick="closeSK()"
            style="width:100%;padding:11px;border:1.5px solid #ddd;border-radius:10px;background:white;color:#666;font-size:14px;cursor:pointer;">
            <i class="fa fa-times"></i>&nbsp; Cancel
        </button>
    </div>
</div>

<style>
@keyframes skShake{
    0%,100%{transform:translateX(0)}
    20%{transform:translateX(-10px)}
    40%{transform:translateX(10px)}
    60%{transform:translateX(-7px)}
    80%{transform:translateX(7px)}
}
.sk-overlay-show{ display:flex !important; }
</style>

<script>
// ── SECRET KEY for Admin button in header ──
var SK_KEY      = 'MIST2026'; // Change this to your secret key
var skTries     = 0;
var SK_MAX      = 3;
var skIsLocked  = false;

function openSecretKey(e){
    e.preventDefault();
    if(skIsLocked){ alert('Access locked. Please try again later.'); return; }
    document.getElementById('skInput').value = '';
    document.getElementById('skError').style.display = 'none';
    document.getElementById('skAttempts').textContent = '';
    var ov = document.getElementById('skOverlay');
    ov.style.display = 'flex';
    setTimeout(function(){ document.getElementById('skInput').focus(); }, 150);
}

function closeSK(){
    document.getElementById('skOverlay').style.display = 'none';
}

function toggleSKShow(){
    var inp  = document.getElementById('skInput');
    var icon = document.getElementById('skEyeIcon');
    if(inp.type === 'password'){
        inp.type = 'text';
        icon.className = 'fa fa-eye-slash';
    } else {
        inp.type = 'password';
        icon.className = 'fa fa-eye';
    }
}

function verifyKey(){
    var val = document.getElementById('skInput').value.trim();
    var err = document.getElementById('skError');
    var att = document.getElementById('skAttempts');

    if(!val){
        err.textContent = 'Please enter the secret key.';
        err.style.display = 'block';
        return;
    }

    if(val === SK_KEY){
        closeSK();
        window.location.href = 'login.php';
    } else {
        skTries++;
        var box = document.getElementById('skBox');
        box.style.animation = 'none';
        void box.offsetWidth;
        box.style.animation = 'skShake 0.4s ease';

        if(skTries >= SK_MAX){
            skIsLocked = true;
            err.textContent = 'Too many failed attempts. Access locked for 1 minute.';
            err.style.display = 'block';
            att.textContent = '';
            setTimeout(function(){ skTries = 0; skIsLocked = false; closeSK(); }, 60000);
        } else {
            err.textContent = 'Incorrect secret key.';
            err.style.display = 'block';
            att.textContent = (SK_MAX - skTries) + ' attempt(s) remaining.';
        }
        document.getElementById('skInput').value = '';
        document.getElementById('skInput').focus();
    }
}

document.getElementById('skOverlay').addEventListener('click', function(e){
    if(e.target === this) closeSK();
});
</script>

</body>
</html>
