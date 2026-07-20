<?php
session_start();
if(!isset($_SESSION['admin'])){ header("Location: login.php"); exit; }
include 'db.php';

$addMsg = $_SESSION['flash_message'] ?? "";
unset($_SESSION['flash_message']);
if(isset($_POST['add_student'])){
    $code = mysqli_real_escape_string($conn, strtoupper(trim($_POST['student_code'])));
    $name = mysqli_real_escape_string($conn, strtoupper(trim($_POST['full_name'])));
    $days = mysqli_real_escape_string($conn, implode(',', $_POST['allowed_days'] ?? []));
    if($code && $name && $days){
        $chk = mysqli_query($conn,"SELECT id FROM students WHERE student_code='$code'");
        if(mysqli_num_rows($chk) > 0){
            $addMsg = '<div class="alert alert-error">Student ID already exists.</div>';
        } else {
            mysqli_query($conn,"INSERT INTO students(student_code,full_name,allowed_days) VALUES('$code','$name','$days')");
            $addMsg = '<div class="alert alert-success">Student added successfully.</div>';
        }
    } else {
        $addMsg = '<div class="alert alert-error">All fields are required.</div>';
    }
}

if(isset($_POST['delete_student'])){
    $id = intval($_POST['del_id']);
    mysqli_begin_transaction($conn);
    try {
        $studentResult = mysqli_query($conn, "SELECT student_code FROM students WHERE id=$id FOR UPDATE");
        $student = $studentResult ? mysqli_fetch_assoc($studentResult) : null;
        if(!$student) throw new Exception('Student was not found.');

        $studentCode = mysqli_real_escape_string($conn, $student['student_code']);
        if(!mysqli_query($conn, "DELETE FROM attendance WHERE student_code='$studentCode'")) {
            throw new Exception('Could not delete attendance records.');
        }
        if(!mysqli_query($conn, "DELETE FROM students WHERE id=$id")) {
            throw new Exception('Could not delete student.');
        }
        mysqli_commit($conn);
        $_SESSION['flash_message'] = '<div class="alert alert-success">Student and their attendance records were deleted.</div>';
        header('Location: dashboard.php?section=students');
        exit;
    } catch(Exception $e) {
        mysqli_rollback($conn);
        $addMsg = '<div class="alert alert-error">Unable to delete student. Please try again.</div>';
    }
}

$totalStudents   = mysqli_num_rows(mysqli_query($conn,"SELECT id FROM students"));
$totalAttendance = mysqli_num_rows(mysqli_query($conn,"SELECT id FROM attendance"));
$todayCount      = mysqli_num_rows(mysqli_query($conn,"SELECT id FROM attendance WHERE attendance_date=CURDATE()"));
$allowedToday    = mysqli_num_rows(mysqli_query($conn,"SELECT id FROM attendance WHERE attendance_date=CURDATE() AND status='ALLOWED'"));

$studentsResult = mysqli_query($conn,"SELECT * FROM students ORDER BY YEAR(created_at) DESC, student_code ASC");
$studentsList   = [];
while($r = mysqli_fetch_assoc($studentsResult)) $studentsList[] = $r;

// Fingerprint enrollment counts per student (graceful fallback if the
// webauthn_credentials table hasn't been created yet on this install).
$fingerprintCounts = [];
$fpResult = @mysqli_query($conn, "SELECT student_code, COUNT(*) as cnt FROM webauthn_credentials GROUP BY student_code");
if($fpResult){
    while($row = mysqli_fetch_assoc($fpResult)){
        $fingerprintCounts[$row['student_code']] = intval($row['cnt']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Admin Dashboard</title>
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:Arial,Helvetica,sans-serif;}
body{background:#f0f2f5;}
.layout{display:flex;min-height:100vh;}
.sidebar{width:240px;background:#003366;color:white;display:flex;flex-direction:column;position:fixed;top:0;left:0;height:100vh;z-index:100;}
.sidebar-logo{padding:24px 20px 20px;border-bottom:1px solid rgba(255,255,255,0.1);text-align:center;}
.sidebar-logo h2{font-size:16px;line-height:1.4;}
.sidebar-logo p{font-size:11px;opacity:0.7;margin-top:4px;}
.sidebar nav{flex:1;padding:16px 0;}
.sidebar nav a{display:flex;align-items:center;gap:12px;color:white;text-decoration:none;padding:12px 20px;font-size:14px;transition:background 0.2s;cursor:pointer;border:none;background:none;width:100%;}
.sidebar nav a:hover,.sidebar nav a.active{background:rgba(255,255,255,0.15);}
.sidebar nav a i{width:18px;text-align:center;}
.sidebar-footer{padding:16px 20px;border-top:1px solid rgba(255,255,255,0.1);font-size:12px;opacity:0.7;}
.sidebar-footer a{color:white;text-decoration:none;}
.main{margin-left:240px;flex:1;display:flex;flex-direction:column;}
.topbar{background:white;padding:16px 24px;display:flex;justify-content:space-between;align-items:center;box-shadow:0 1px 4px rgba(0,0,0,0.08);position:sticky;top:0;z-index:50;}
.topbar h1{font-size:18px;color:#003366;}
.topbar .admin-info{font-size:13px;color:#666;}
.content{padding:24px;}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:24px;}
.stat-card{background:white;border-radius:10px;padding:20px;box-shadow:0 1px 6px rgba(0,0,0,0.07);display:flex;align-items:center;gap:14px;}
.stat-icon{width:48px;height:48px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;color:white;flex-shrink:0;}
.stat-card h3{font-size:24px;color:#003366;}
.stat-card p{font-size:12px;color:#888;margin-top:2px;}
.section{display:none;}
.section.active{display:block;}
.card{background:white;border-radius:10px;padding:22px;box-shadow:0 1px 6px rgba(0,0,0,0.07);margin-bottom:20px;}
.card h2{color:#003366;margin-bottom:16px;font-size:17px;border-bottom:2px solid #f0f0f0;padding-bottom:10px;}
.form-row{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:14px;}
.form-group label{display:block;font-size:12px;color:#555;font-weight:600;margin-bottom:5px;}
.form-group input,.form-group select{width:100%;padding:10px 12px;border:1.5px solid #ddd;border-radius:6px;font-size:13px;}
.btn{display:inline-block;padding:10px 20px;border:none;border-radius:6px;cursor:pointer;font-weight:600;font-size:13px;transition:all 0.2s;}
.btn-primary{background:#003366;color:white;}
.btn-primary:hover{background:#00509e;}
.btn-danger{background:#dc3545;color:white;}
.btn-danger:hover{background:#c82333;}
.btn-success{background:#28a745;color:white;}
.btn-success:hover{background:#218838;}
.btn-purple{background:#6f42c1;color:white;}
.btn-purple:hover{background:#5a32a3;}
.btn-warning{background:#e0a800;color:white;}
.btn-warning:hover{background:#c69500;}
.btn-pdf{background:#c0392b;color:white;}
.btn-pdf:hover{background:#a93226;}
.btn-sm{padding:6px 12px;font-size:12px;}
table{width:100%;border-collapse:collapse;font-size:13px;}
table th{background:#003366;color:white;padding:10px 12px;text-align:left;}
table td{padding:9px 12px;border-bottom:1px solid #f0f0f0;}
table tr:hover td{background:#f8f9fa;}
.allowed{color:green;font-weight:bold;}
.denied{color:red;font-weight:bold;}
.filter-bar{display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:16px;}
.filter-group{display:flex;flex-direction:column;gap:4px;flex:1;min-width:120px;}
.filter-group label{font-size:12px;font-weight:700;color:#003366;}
.filter-group select,.filter-group input{width:100%;padding:8px 12px;border:1.5px solid #ddd;border-radius:6px;font-size:13px;}
.filter-btns{display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;}
.filter-mode{display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap;}
.mode-btn{padding:7px 14px;border-radius:20px;border:2px solid #003366;background:white;color:#003366;font-size:12px;font-weight:600;cursor:pointer;transition:all 0.2s;}
.mode-btn.active{background:#003366;color:white;}
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:999;align-items:center;justify-content:center;}
.modal-overlay.show{display:flex;}
.modal{background:white;border-radius:14px;padding:36px;text-align:center;max-width:340px;width:90%;box-shadow:0 20px 50px rgba(0,0,0,0.3);}
.modal h3{color:#003366;margin-bottom:6px;}
.modal p{color:#666;font-size:13px;margin-bottom:16px;}
#qrCanvas{display:flex;justify-content:center;margin-bottom:20px;}
.qr-toolbar{display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin-bottom:18px;}
.qr-toolbar p{font-size:13px;color:#666;}
.qr-gallery{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:16px;}
.qr-card{border:1px solid #dce4ec;border-radius:10px;padding:14px;text-align:center;background:#fff;break-inside:avoid;page-break-inside:avoid;}
.qr-card canvas,.qr-card img{width:150px!important;height:150px!important;margin:8px auto;display:block;}
.qr-card-name{font-size:13px;font-weight:700;color:#003366;line-height:1.35;min-height:35px;display:flex;align-items:center;justify-content:center;}
.qr-card-code{font-size:12px;color:#666;margin-top:5px;letter-spacing:.3px;}
.qr-empty{padding:30px;text-align:center;color:#888;border:1px dashed #ccc;border-radius:8px;}
.student-year-group td{background:#e8f0f8!important;color:#003366;font-weight:700;font-size:14px;padding:12px!important;border-top:2px solid #003366;}
.summary-bar{background:#e0e0e0;border-radius:3px;height:8px;margin-top:4px;}
.summary-bar-fill{background:#003366;border-radius:3px;height:8px;}
.alert{padding:10px 14px;border-radius:6px;margin-bottom:14px;font-size:13px;}
.alert-success{background:#d4edda;color:#155724;}
.alert-error{background:#f8d7da;color:#721c24;}
input[type=text],input[type=password]{width:100%;padding:9px 12px;border:1.5px solid #ddd;border-radius:6px;font-size:13px;}
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
@media print {
  body *{visibility:hidden!important;}
  #qrPrintArea,#qrPrintArea *{visibility:visible!important;}
  #qrPrintArea{position:absolute;left:0;top:0;width:100%;}
  #qrPrintArea .qr-gallery{grid-template-columns:repeat(3,1fr);gap:8mm;}
  #qrPrintArea .qr-card{border:1px solid #bbb;padding:5mm;}
  #qrPrintArea .qr-card canvas,#qrPrintArea .qr-card img{width:38mm!important;height:38mm!important;}
}
@page{size:A4 portrait;margin:10mm;}
</style>
</head>
<body>
<div class="layout">

<div class="sidebar">
  <div class="sidebar-logo">
    <h2>MIST<br>Admin Panel</h2>
    <p>Attendance System</p>
  </div>
  <nav>
    <a id="nav-dashboard" onclick="showSection('dashboard')"><i class="fa fa-gauge"></i> Dashboard</a>
    <a id="nav-students"  onclick="showSection('students')"><i class="fa fa-users"></i> Manage Students</a>
    <a id="nav-qrcodes"   onclick="showSection('qrcodes')"><i class="fa fa-qrcode"></i> QR Codes</a>
    <a id="nav-attendance" onclick="showSection('attendance')"><i class="fa fa-list-check"></i> Attendance Records</a>
    <a id="nav-summary"   onclick="showSection('summary')"><i class="fa fa-chart-bar"></i> Monthly Summary</a>
  </nav>
  <div class="sidebar-footer">
    Logged in as <strong><?= htmlspecialchars($_SESSION['admin']) ?></strong><br>
    <a href="logout.php"><i class="fa fa-right-from-bracket"></i> Logout</a>
    &nbsp;|&nbsp;
    <a href="index.php" target="_blank"><i class="fa fa-external-link"></i> Front Page</a>
    <a href="scanner.php" target="_blank"><i class="fa fa-qrcode"></i> QR Scanner</a>
  </div>
</div>

<div class="main">
<div class="topbar">
  <h1 id="pageTitle">Dashboard</h1>
  <div class="admin-info">
    <i class="fa fa-user-shield"></i>
    <?= htmlspecialchars($_SESSION['admin']) ?> &nbsp;|&nbsp; <?= date('l, d M Y') ?>
  </div>
</div>

<div class="content">

<!-- DASHBOARD -->
<div class="section active" id="sec-dashboard">
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-icon" style="background:#003366"><i class="fa fa-users"></i></div>
      <div><h3><?= $totalStudents ?></h3><p>Total Students</p></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#00509e"><i class="fa fa-calendar-check"></i></div>
      <div><h3><?= $totalAttendance ?></h3><p>Total Records</p></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#28a745"><i class="fa fa-clock"></i></div>
      <div><h3><?= $todayCount ?></h3><p>Today Scans</p></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#17a2b8"><i class="fa fa-check-circle"></i></div>
      <div><h3><?= $allowedToday ?></h3><p>Allowed Today</p></div>
    </div>
  </div>
  <div class="card">
    <h2><i class="fa fa-clock"></i> Today — <?= date('l, d M Y') ?></h2>
    <table>
      <thead><tr><th>#</th><th>Student ID</th><th>Name</th><th>Day</th><th>Status</th><th>Time</th></tr></thead>
      <tbody>
      <?php
      $tr = mysqli_query($conn,"SELECT * FROM attendance WHERE attendance_date=CURDATE() ORDER BY id DESC");
      $n=1;
      while($r=mysqli_fetch_assoc($tr)){
        $c=$r['status']==='ALLOWED'?'allowed':'denied';
        echo "<tr><td>$n</td><td>{$r['student_code']}</td><td>".htmlspecialchars($r['full_name'])."</td><td>{$r['attendance_day']}</td><td class='$c'>{$r['status']}</td><td>".date('h:i A',strtotime($r['attendance_time']))."</td></tr>";
        $n++;
      }
      if($n===1) echo '<tr><td colspan="6" style="text-align:center;color:#aaa;padding:20px">No attendance today</td></tr>';
      ?>
      </tbody>
    </table>
  </div>
</div>

<!-- STUDENTS -->
<div class="section" id="sec-students">
  <div class="card">
    <h2><i class="fa fa-user-plus"></i> Add New Student</h2>
    <?= $addMsg ?>
    <form method="POST">
      <div class="form-row">
        <div class="form-group">
          <label>Student Code</label>
          <input type="text" name="student_code" placeholder="MIST036" required/>
        </div>
        <div class="form-group">
          <label>Full Name</label>
          <input type="text" name="full_name" placeholder="SURNAME FIRSTNAME" required/>
        </div>
        <div class="form-group">
          <label>Allowed Days (hold Ctrl to select multiple)</label>
          <select name="allowed_days[]" multiple required style="height:90px">
            <option value="Monday">Monday</option>
            <option value="Tuesday">Tuesday</option>
            <option value="Wednesday">Wednesday</option>
            <option value="Thursday">Thursday</option>
            <option value="Friday">Friday</option>
          </select>
        </div>
      </div>
      <button type="submit" name="add_student" class="btn btn-primary">
        <i class="fa fa-plus"></i> Add Student
      </button>
    </form>
  </div>
  <div class="card">
    <h2><i class="fa fa-users"></i> Student List (<?= count($studentsList) ?>)</h2>
    <input type="text" id="studentSearch" placeholder="Search by name or ID..."
      onkeyup="filterStudentTable()"
      style="margin-bottom:14px;padding:9px 12px;width:100%;border:1.5px solid #ddd;border-radius:6px;font-size:13px"/>
    <table id="studentTable">
      <thead><tr><th>#</th><th>Code</th><th>Name</th><th>Allowed Days</th><th>PIN</th><th>Fingerprint</th><th>Actions</th></tr></thead>
      <tbody>
      <?php $currentEntryYear = null; $yearStudentNumber = 0; foreach($studentsList as $i=>$s): ?>
      <?php $entryYear = !empty($s['created_at']) ? date('Y', strtotime($s['created_at'])) : 'Unknown Year'; ?>
      <?php if($entryYear !== $currentEntryYear): $currentEntryYear = $entryYear; $yearStudentNumber = 0; ?>
      <tr class="student-year-group" data-year="<?= htmlspecialchars($entryYear) ?>">
        <td colspan="7"><i class="fa fa-calendar"></i> Students Added in <?= htmlspecialchars($entryYear) ?></td>
      </tr>
      <?php endif; ?>
      <?php $yearStudentNumber++; ?>
      <tr class="student-row" data-year="<?= htmlspecialchars($entryYear) ?>">
        <td><?= $yearStudentNumber ?></td>
        <td><?= htmlspecialchars($s['student_code']) ?></td>
        <td><?= htmlspecialchars($s['full_name']) ?></td>
        <td><?= htmlspecialchars($s['allowed_days']) ?></td>
        <td><?= !empty($s['pin']) ? '<span style="color:green;font-weight:bold">Set</span>' : '<span style="color:#aaa">Not Set</span>' ?></td>
        <td id="fp-status-<?= htmlspecialchars($s['student_code']) ?>">
          <?php $fpCount = $fingerprintCounts[$s['student_code']] ?? 0; ?>
          <?php if($fpCount > 0): ?>
            <span style="color:green;font-weight:bold">
              <i class="fa fa-fingerprint"></i> Enrolled<?= $fpCount > 1 ? " ($fpCount)" : "" ?>
            </span>
            <button class="btn btn-danger btn-sm" style="margin-left:6px"
              onclick="deleteFingerprint('<?= htmlspecialchars($s['student_code'], ENT_QUOTES) ?>','<?= addslashes($s['full_name']) ?>')">
              <i class="fa fa-trash"></i>
            </button>
          <?php else: ?>
            <span style="color:#aaa">Not Enrolled</span>
          <?php endif; ?>
        </td>
        <td>
          <button class="btn btn-warning btn-sm"
            onclick="openEditModal('<?= htmlspecialchars($s['student_code'], ENT_QUOTES) ?>','<?= addslashes($s['full_name']) ?>','<?= htmlspecialchars($s['allowed_days'], ENT_QUOTES) ?>')">
            <i class="fa fa-pen"></i> Edit
          </button>
          <button class="btn btn-success btn-sm"
            onclick="generateQR('<?= $s['student_code'] ?>','<?= addslashes($s['full_name']) ?>')">
            <i class="fa fa-qrcode"></i> QR
          </button>
          <button class="btn btn-purple btn-sm"
            data-pin="<?= $s['student_code'] ?>"
            onclick="openPINModal('<?= $s['student_code'] ?>','<?= addslashes($s['full_name']) ?>')">
            <i class="fa fa-key"></i> Set PIN
          </button>
          <form method="POST" style="display:inline" onsubmit="return confirm('Delete this student?')">
            <input type="hidden" name="del_id" value="<?= $s['id'] ?>"/>
            <button type="submit" name="delete_student" class="btn btn-danger btn-sm">
              <i class="fa fa-trash"></i>
            </button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- QR CODES -->
<div class="section" id="sec-qrcodes">
  <div class="card">
    <h2><i class="fa fa-qrcode"></i> All Student QR Codes</h2>
    <div class="qr-toolbar">
      <p>View every student QR code below. Print uses A4 pages; the PDF contains the same layout.</p>
      <div>
        <button class="btn btn-primary btn-sm" onclick="printAllQRCodes()"><i class="fa fa-print"></i> Print All (A4)</button>
        <button class="btn btn-pdf btn-sm" onclick="exportAllQRCodesPDF()"><i class="fa fa-file-pdf"></i> Export All to PDF</button>
      </div>
    </div>
    <div id="qrPrintArea"><div id="allQrGallery" class="qr-gallery"><div class="qr-empty">Loading QR codes...</div></div></div>
  </div>
  <div class="card">
    <h2><i class="fa fa-qrcode"></i> View Individual QR Code</h2>
    <table>
      <thead><tr><th>#</th><th>Code</th><th>Name</th><th>Allowed Days</th><th>Action</th></tr></thead>
      <tbody>
      <?php foreach($studentsList as $i=>$s): ?>
      <tr>
        <td><?= $i+1 ?></td>
        <td><?= htmlspecialchars($s['student_code']) ?></td>
        <td><?= htmlspecialchars($s['full_name']) ?></td>
        <td><?= htmlspecialchars($s['allowed_days']) ?></td>
        <td>
          <button class="btn btn-primary btn-sm"
            onclick="generateQR('<?= $s['student_code'] ?>','<?= addslashes($s['full_name']) ?>')">
            <i class="fa fa-qrcode"></i> Generate &amp; View
          </button>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ATTENDANCE -->
<div class="section" id="sec-attendance">
  <div class="card">
    <h2><i class="fa fa-list-check"></i> All Attendance Records</h2>
    <div class="filter-mode">
      <button class="mode-btn active" id="adminBtnToday" onclick="setAdminMode('today')"><i class="fa fa-calendar-day"></i> Today</button>
      <button class="mode-btn" id="adminBtnDate"  onclick="setAdminMode('date')"><i class="fa fa-calendar"></i> Specific Date</button>
      <button class="mode-btn" id="adminBtnMonth" onclick="setAdminMode('month')"><i class="fa fa-calendar-alt"></i> Month/Year</button>
    </div>
    <div id="adminFilterToday" class="filter-bar">
      <div class="filter-group"><label>Showing today only</label></div>
      <div class="filter-btns">
        <button class="btn btn-primary btn-sm" onclick="loadAdminRecords()"><i class="fa fa-rotate"></i> Refresh</button>
        <button class="btn btn-pdf btn-sm" onclick="exportPDF()"><i class="fa fa-file-pdf"></i> Export PDF</button>
      </div>
    </div>
    <div id="adminFilterDate" class="filter-bar" style="display:none">
      <div class="filter-group"><label>Select Date</label><input type="date" id="adminSpecificDate"/></div>
      <div class="filter-group"><label>Search Student</label><input type="text" id="adminSearchDate" placeholder="Name or ID..."/></div>
      <div class="filter-btns">
        <button class="btn btn-primary btn-sm" onclick="loadAdminRecords()"><i class="fa fa-filter"></i> Apply</button>
        <button class="btn btn-pdf btn-sm" onclick="exportPDF()"><i class="fa fa-file-pdf"></i> Export PDF</button>
        <button class="btn btn-sm" style="background:#6c757d;color:white" onclick="setAdminMode('today')"><i class="fa fa-times"></i> Clear</button>
      </div>
    </div>
    <div id="adminFilterMonth" class="filter-bar" style="display:none">
      <div class="filter-group"><label>Year</label><select id="adminFilterYear"></select></div>
      <div class="filter-group"><label>Month</label><select id="adminFilterMonthSel"></select></div>
      <div class="filter-group"><label>Search Student</label><input type="text" id="adminSearchMonth" placeholder="Name or ID..."/></div>
      <div class="filter-btns">
        <button class="btn btn-primary btn-sm" onclick="loadAdminRecords()"><i class="fa fa-filter"></i> Apply</button>
        <button class="btn btn-pdf btn-sm" onclick="exportPDF()"><i class="fa fa-file-pdf"></i> Export PDF</button>
        <button class="btn btn-sm" style="background:#6c757d;color:white" onclick="setAdminMode('today')"><i class="fa fa-times"></i> Clear</button>
      </div>
    </div>
    <div id="adminRecordCount" style="font-size:13px;color:#666;margin-bottom:10px"></div>
    <table id="adminAttTable">
      <thead><tr><th>#</th><th>Student ID</th><th>Name</th><th>Allowed Days</th><th>Date</th><th>Day</th><th>Status</th><th>Time</th><th>Via</th></tr></thead>
      <tbody id="adminAttBody"></tbody>
    </table>
  </div>
</div>

<!-- SUMMARY -->
<div class="section" id="sec-summary">
  <div class="card">
    <h2><i class="fa fa-chart-bar"></i> Monthly Attendance Summary</h2>
    <div class="filter-bar" style="margin-bottom:20px">
      <div class="filter-group"><label>Year</label><select id="sumYear"></select></div>
      <div class="filter-group"><label>Month</label><select id="sumMonth"></select></div>
      <div class="filter-btns">
        <button class="btn btn-primary btn-sm" onclick="loadSummary()"><i class="fa fa-chart-bar"></i> Generate</button>
        <button class="btn btn-pdf btn-sm" onclick="exportSummaryPDF()"><i class="fa fa-file-pdf"></i> Export PDF</button>
      </div>
    </div>
    <div id="summaryContent"></div>
  </div>
</div>

</div>
</div>
</div>

<!-- QR MODAL -->
<div class="modal-overlay" id="qrModal">
  <div class="modal">
    <h3 id="qrStudentName"></h3>
    <p id="qrStudentCode"></p>
    <div id="qrCanvas"></div>
    <button class="btn btn-primary" onclick="printQR()"><i class="fa fa-print"></i> Print QR</button>
    <button class="btn" style="background:#6c757d;color:white;margin-left:8px" onclick="closeQR()">Close</button>
  </div>
</div>

<!-- PIN MODAL -->
<div class="modal-overlay" id="pinModal">
  <div class="modal" style="max-width:380px">
    <h3>Manage Student PIN</h3>
    <p id="pinModalStudent" style="color:#00509e;font-weight:700;margin-bottom:10px;font-size:14px"></p>
    <p>Enter a 4-digit PIN for this student.</p>
    <input type="text" id="pinInput" maxlength="4" placeholder="e.g. 1234"
      style="width:100%;padding:14px;border:2px solid #ddd;border-radius:8px;font-size:24px;text-align:center;letter-spacing:12px;margin:12px 0"/>
    <div id="pinModalError" style="color:#dc3545;font-size:13px;margin-bottom:10px;display:none;"></div>
    <button class="btn btn-primary" style="width:100%;margin-bottom:8px" onclick="submitPIN()">
      <i class="fa fa-save"></i> Save PIN
    </button>
    <button class="btn btn-danger" style="width:100%;margin-bottom:8px" onclick="deletePIN()">
      <i class="fa fa-trash"></i> Delete PIN
    </button>
    <button class="btn" style="background:#6c757d;color:white;width:100%" onclick="closePINModal()">Cancel</button>
  </div>
</div>

<!-- EDIT STUDENT MODAL -->
<div class="modal-overlay" id="editModal">
  <div class="modal" style="max-width:420px">
    <h3>Edit Student</h3>
    <p id="editModalCode" style="color:#00509e;font-weight:700;margin-bottom:14px;font-size:14px"></p>

    <div class="form-group" style="margin-bottom:14px">
      <label>Student Code</label>
      <input type="text" id="editStudentCode" placeholder="MIST017"
        style="width:100%;padding:10px 12px;border:1.5px solid #ddd;border-radius:6px;font-size:14px;margin-top:6px;text-transform:uppercase"/>
      <small style="color:#999;font-size:11px">Changing this updates the student's QR code, PIN, fingerprint, and all attendance history to match.</small>
    </div>

    <div class="form-group" style="margin-bottom:14px">
      <label>Full Name</label>
      <input type="text" id="editFullName" placeholder="SURNAME FIRSTNAME"
        style="width:100%;padding:10px 12px;border:1.5px solid #ddd;border-radius:6px;font-size:14px;margin-top:6px"/>
    </div>

    <div class="form-group" style="margin-bottom:14px">
      <label>Allowed Days (hold Ctrl to select multiple)</label>
      <select id="editAllowedDays" multiple style="height:110px;width:100%;padding:10px 12px;border:1.5px solid #ddd;border-radius:6px;font-size:14px;margin-top:6px">
        <option value="Monday">Monday</option>
        <option value="Tuesday">Tuesday</option>
        <option value="Wednesday">Wednesday</option>
        <option value="Thursday">Thursday</option>
        <option value="Friday">Friday</option>
      </select>
    </div>

    <div id="editModalError" style="color:#dc3545;font-size:13px;margin-bottom:10px;display:none;"></div>

    <button class="btn btn-primary" style="width:100%;margin-bottom:8px" onclick="saveEditStudent()">
      <i class="fa fa-save"></i> Save Changes
    </button>
    <button class="btn" style="background:#6c757d;color:white;width:100%" onclick="closeEditModal()">Cancel</button>
  </div>
</div>

<script>
var MONTHS = ["January","February","March","April","May","June","July","August","September","October","November","December"];
var NOW = new Date();
var todayISO = NOW.toISOString().split('T')[0];
var qrStudents = <?= json_encode(array_map(function($student){ return ['code'=>$student['student_code'], 'name'=>$student['full_name']]; }, $studentsList), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
var allQRCodesRendered = false;

// ── SECTION NAV ──
function showSection(name) {
    var sections = ['dashboard','students','qrcodes','attendance','summary'];
    for (var i = 0; i < sections.length; i++) {
        document.getElementById('sec-' + sections[i]).classList.remove('active');
        document.getElementById('nav-' + sections[i]).classList.remove('active');
    }
    document.getElementById('sec-' + name).classList.add('active');
    document.getElementById('nav-' + name).classList.add('active');
    var titles = {dashboard:'Dashboard',students:'Manage Students',qrcodes:'QR Codes',attendance:'Attendance Records',summary:'Monthly Summary'};
    document.getElementById('pageTitle').textContent = titles[name];
    if (name === 'qrcodes') renderAllQRCodes();
    if (name === 'attendance') loadAdminRecords();
    if (name === 'summary') initSummaryFilters();
}

// Keep the admin on the relevant page after a form action without
// resubmitting that action if the browser is refreshed.
var requestedSection = new URLSearchParams(window.location.search).get('section');
if (requestedSection === 'students') showSection('students');

// ── YEAR/MONTH SELECTS ──
function buildYearSelect(id) {
    var sel = document.getElementById(id); sel.innerHTML = '';
    for (var y = NOW.getFullYear(); y >= NOW.getFullYear() - 4; y--) {
        var o = document.createElement('option'); o.value = y; o.textContent = y;
        if (y === NOW.getFullYear()) o.selected = true;
        sel.appendChild(o);
    }
}
function buildMonthSelect(id) {
    var sel = document.getElementById(id); sel.innerHTML = '';
    for (var i = 0; i < MONTHS.length; i++) {
        var o = document.createElement('option'); o.value = i + 1; o.textContent = MONTHS[i];
        if (i === NOW.getMonth()) o.selected = true;
        sel.appendChild(o);
    }
}
buildYearSelect('adminFilterYear');
buildMonthSelect('adminFilterMonthSel');
document.getElementById('adminSpecificDate').value = todayISO;
document.getElementById('adminSpecificDate').max   = todayISO;

// ── ADMIN FILTER MODE ──
var adminMode = 'today';
function setAdminMode(mode) {
    adminMode = mode;
    document.getElementById('adminFilterToday').style.display = mode === 'today' ? 'flex' : 'none';
    document.getElementById('adminFilterDate').style.display  = mode === 'date'  ? 'flex' : 'none';
    document.getElementById('adminFilterMonth').style.display = mode === 'month' ? 'flex' : 'none';
    document.getElementById('adminBtnToday').classList.toggle('active', mode === 'today');
    document.getElementById('adminBtnDate').classList.toggle('active',  mode === 'date');
    document.getElementById('adminBtnMonth').classList.toggle('active', mode === 'month');
    loadAdminRecords();
}

// ── LOAD RECORDS ──
function loadAdminRecords() {
    var url = 'get_records.php';
    var search = '';
    if (adminMode === 'today') {
        url += '?date=' + todayISO;
    } else if (adminMode === 'date') {
        var d = document.getElementById('adminSpecificDate').value;
        url += '?date=' + (d || todayISO);
        search = document.getElementById('adminSearchDate').value.toLowerCase();
    } else {
        url += '?year=' + document.getElementById('adminFilterYear').value + '&month=' + document.getElementById('adminFilterMonthSel').value;
        search = document.getElementById('adminSearchMonth').value.toLowerCase();
    }
    fetch(url).then(function(r){ return r.json(); }).then(function(data){
        var filtered = search ? data.filter(function(r){ return r.student_code.toLowerCase().includes(search) || (r.full_name||'').toLowerCase().includes(search); }) : data;
        var body = document.getElementById('adminAttBody');
        body.innerHTML = '';
        document.getElementById('adminRecordCount').textContent = 'Showing ' + filtered.length + ' record(s)';
        filtered.forEach(function(r, i){
            var cls = r.status === 'ALLOWED' ? 'allowed' : 'denied';
            body.innerHTML += '<tr><td>'+(i+1)+'</td><td>'+r.student_code+'</td><td>'+(r.full_name||'')+'</td><td class="allowed">'+(r.allowed_days||'')+'</td><td>'+r.attendance_date+'</td><td>'+r.attendance_day+'</td><td class="'+cls+'">'+r.status+'</td><td>'+r.attendance_time+'</td><td>'+(r.source||'qr')+'</td></tr>';
        });
        if (filtered.length === 0) body.innerHTML = '<tr><td colspan="9" style="text-align:center;color:#aaa;padding:20px">No records found</td></tr>';
    });
}
loadAdminRecords();

// ── SUMMARY ──
function initSummaryFilters() { buildYearSelect('sumYear'); buildMonthSelect('sumMonth'); }
function loadSummary() {
    var year = document.getElementById('sumYear').value;
    var month = document.getElementById('sumMonth').value;
    var monthName = MONTHS[parseInt(month) - 1];
    fetch('get_records.php?year=' + year + '&month=' + month).then(function(r){ return r.json(); }).then(function(data){
        var map = {};
        data.forEach(function(r){
            if (!map[r.student_code]) map[r.student_code] = {code:r.student_code,name:r.full_name||r.student_code,allowed:0,denied:0,total:0};
            map[r.student_code].total++;
            if (r.status === 'ALLOWED') map[r.student_code].allowed++;
            else map[r.student_code].denied++;
        });
        var rows = Object.values(map).sort(function(a,b){ return a.code.localeCompare(b.code); });
        var maxA = Math.max.apply(null, rows.map(function(r){ return r.allowed; }).concat([1]));
        var html = '<h3 style="color:#003366;margin-bottom:14px">'+monthName+' '+year+' — '+rows.length+' student(s)</h3>';
        if (rows.length === 0) {
            html += '<p style="color:#aaa;text-align:center;padding:30px">No data for this period.</p>';
        } else {
            html += '<table><thead><tr><th>#</th><th>Student ID</th><th>Name</th><th>Total</th><th>Allowed</th><th>Denied</th><th>Rate</th></tr></thead><tbody>';
            rows.forEach(function(r, i){
                var rate = r.total > 0 ? Math.round(r.allowed / r.total * 100) : 0;
                var barW = Math.round(r.allowed / maxA * 100);
                html += '<tr><td>'+(i+1)+'</td><td>'+r.code+'</td><td>'+r.name+'</td><td style="text-align:center">'+r.total+'</td><td style="text-align:center;color:green;font-weight:bold">'+r.allowed+'</td><td style="text-align:center;color:red;font-weight:bold">'+r.denied+'</td><td><div style="font-size:12px;margin-bottom:3px">'+rate+'%</div><div class="summary-bar"><div class="summary-bar-fill" style="width:'+barW+'%"></div></div></td></tr>';
            });
            html += '</tbody></table>';
        }
        document.getElementById('summaryContent').innerHTML = html;
    });
}

// ── PDF EXPORT ──
function exportPDF() {
    var jsPDF = window.jspdf.jsPDF;
    var doc = new jsPDF('landscape');
    doc.setFontSize(16); doc.setTextColor(0,51,102);
    doc.text('MIST SIWES Attendance Records', 14, 18);
    doc.setFontSize(10); doc.setTextColor(100);
    var info = adminMode === 'today' ? 'Date: Today ('+new Date().toLocaleDateString()+')'
             : adminMode === 'date'  ? 'Date: '+document.getElementById('adminSpecificDate').value
             : 'Period: '+MONTHS[parseInt(document.getElementById('adminFilterMonthSel').value)-1]+' '+document.getElementById('adminFilterYear').value;
    doc.text(info, 14, 26);
    doc.text('Generated: '+new Date().toLocaleString(), 14, 32);
    var rows = [];
    document.querySelectorAll('#adminAttBody tr').forEach(function(tr){
        var cells = tr.querySelectorAll('td');
        if (cells.length > 1) rows.push(Array.from(cells).map(function(c){ return c.textContent; }));
    });
    doc.autoTable({ startY:38, head:[['#','Student ID','Name','Allowed Days','Date','Day','Status','Time','Via']], body:rows, styles:{fontSize:9,cellPadding:3}, headStyles:{fillColor:[0,51,102],textColor:255,fontStyle:'bold'}, alternateRowStyles:{fillColor:[245,245,245]} });
    doc.save('attendance.pdf');
}
function exportSummaryPDF() {
    var jsPDF = window.jspdf.jsPDF;
    var doc = new jsPDF();
    var year = document.getElementById('sumYear').value;
    var month = document.getElementById('sumMonth').value;
    doc.setFontSize(16); doc.setTextColor(0,51,102);
    doc.text('MIST SIWES Monthly Summary', 14, 18);
    doc.setFontSize(10); doc.setTextColor(100);
    doc.text('Period: '+MONTHS[parseInt(month)-1]+' '+year, 14, 26);
    var rows = [];
    document.querySelectorAll('#summaryContent table tbody tr').forEach(function(tr){
        var cells = tr.querySelectorAll('td');
        if (cells.length >= 6) rows.push([cells[0].textContent,cells[1].textContent,cells[2].textContent,cells[3].textContent,cells[4].textContent,cells[5].textContent]);
    });
    if (rows.length === 0) { alert('Click Generate Summary first.'); return; }
    doc.autoTable({ startY:34, head:[['#','Student ID','Name','Total','Allowed','Denied']], body:rows, styles:{fontSize:10,cellPadding:3}, headStyles:{fillColor:[0,51,102],textColor:255,fontStyle:'bold'}, alternateRowStyles:{fillColor:[245,245,245]} });
    doc.save('summary_'+MONTHS[parseInt(month)-1]+'_'+year+'.pdf');
}

// ── STUDENT SEARCH ──
function filterStudentTable() {
    var q = document.getElementById('studentSearch').value.toLowerCase();
    document.querySelectorAll('#studentTable tbody .student-row').forEach(function(tr){
        tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
    document.querySelectorAll('#studentTable tbody .student-year-group').forEach(function(header){
        var year = header.getAttribute('data-year');
        var hasVisibleStudent = Array.prototype.some.call(
            document.querySelectorAll('#studentTable tbody .student-row[data-year="' + year + '"]'),
            function(row){ return row.style.display !== 'none'; }
        );
        header.style.display = hasVisibleStudent ? '' : 'none';
    });
}

// ── QR CODE ──
function renderAllQRCodes() {
    if (allQRCodesRendered) return;
    var gallery = document.getElementById('allQrGallery');
    if (!gallery || typeof QRCode === 'undefined') return;
    gallery.innerHTML = '';
    if (qrStudents.length === 0) {
        gallery.innerHTML = '<div class="qr-empty">No students have been added yet.</div>';
        allQRCodesRendered = true;
        return;
    }
    qrStudents.forEach(function(student) {
        var card = document.createElement('div');
        card.className = 'qr-card';
        var name = document.createElement('div');
        name.className = 'qr-card-name';
        name.textContent = student.name;
        var qr = document.createElement('div');
        var code = document.createElement('div');
        code.className = 'qr-card-code';
        code.textContent = 'ID: ' + student.code;
        card.appendChild(name);
        card.appendChild(qr);
        card.appendChild(code);
        gallery.appendChild(card);
        new QRCode(qr, {text:student.code, width:150, height:150, colorDark:'#003366', colorLight:'#ffffff', correctLevel:QRCode.CorrectLevel.H});
    });
    allQRCodesRendered = true;
}
function printAllQRCodes() {
    renderAllQRCodes();
    if (!qrStudents.length) { alert('There are no QR codes to print.'); return; }
    window.print();
}
function exportAllQRCodesPDF() {
    renderAllQRCodes();
    if (!qrStudents.length) { alert('There are no QR codes to export.'); return; }
    var jsPDF = window.jspdf && window.jspdf.jsPDF;
    if (!jsPDF) { alert('PDF export is not available. Please check your internet connection and try again.'); return; }
    var cards = Array.prototype.slice.call(document.querySelectorAll('#allQrGallery .qr-card'));
    var doc = new jsPDF({orientation:'portrait', unit:'mm', format:'a4'});
    var margin = 10, columns = 3, cardWidth = 60, cardHeight = 88;
    cards.forEach(function(card, index) {
        if (index > 0 && index % 9 === 0) doc.addPage();
        var position = index % 9;
        var x = margin + (position % columns) * cardWidth;
        var y = 16 + Math.floor(position / columns) * cardHeight;
        if (position === 0) {
            doc.setFontSize(14); doc.setTextColor(0,51,102);
            doc.text('MIST SIWES Attendance - Student QR Codes', margin, 10);
        }
        var canvas = card.querySelector('canvas');
        var image = card.querySelector('img');
        var imageData = canvas ? canvas.toDataURL('image/png') : (image ? image.src : null);
        if (imageData) doc.addImage(imageData, 'PNG', x + 10, y + 18, 40, 40);
        doc.setTextColor(0,51,102); doc.setFontSize(8); doc.setFont(undefined, 'bold');
        var nameLines = doc.splitTextToSize(card.querySelector('.qr-card-name').textContent, 52);
        doc.text(nameLines, x + 30, y + 7, {maxWidth:52, align:'center'});
        doc.setFont(undefined, 'normal'); doc.setTextColor(90); doc.setFontSize(8);
        doc.text(card.querySelector('.qr-card-code').textContent, x + 30, y + 65, {align:'center'});
        doc.setDrawColor(190); doc.rect(x, y, 58, 75);
    });
    doc.save('MIST_all_student_qr_codes.pdf');
}
function generateQR(code, name) {
    document.getElementById('qrStudentName').textContent = name;
    document.getElementById('qrStudentCode').textContent = 'ID: ' + code;
    document.getElementById('qrCanvas').innerHTML = '';
    new QRCode(document.getElementById('qrCanvas'), {text:code,width:200,height:200,colorDark:'#003366',colorLight:'#ffffff',correctLevel:QRCode.CorrectLevel.H});
    document.getElementById('qrModal').classList.add('show');
}
function closeQR() { document.getElementById('qrModal').classList.remove('show'); }
function printQR() {
    var name = document.getElementById('qrStudentName').textContent;
    var code = document.getElementById('qrStudentCode').textContent;
    var img  = document.querySelector('#qrCanvas img');
    if (!img) { alert('QR not ready, please wait.'); return; }
    var w = window.open('','_blank','width=400,height=500');
    w.document.write('<html><head><title>QR</title><style>body{font-family:Arial;text-align:center;padding:30px;}h2{color:#003366;}</style></head><body><h2>'+name+'</h2><p>'+code+'</p><br><img src="'+img.src+'" width="220"/><br><br><p style="font-size:11px;color:#aaa">MIST SIWES Attendance System</p></body></html>');
    w.document.close(); w.print();
}
document.getElementById('qrModal').addEventListener('click', function(e){ if(e.target===this) closeQR(); });

// ── PIN MANAGEMENT ──
var currentPINStudent = '';
function openPINModal(code, name) {
    currentPINStudent = code;
    document.getElementById('pinModalStudent').textContent = name + ' (' + code + ')';
    document.getElementById('pinInput').value = '';
    document.getElementById('pinModalError').style.display = 'none';
    document.getElementById('pinModal').classList.add('show');
    setTimeout(function(){ document.getElementById('pinInput').focus(); }, 150);
}
function closePINModal() { document.getElementById('pinModal').classList.remove('show'); }

// ── EDIT STUDENT MODAL ──
var currentEditStudent = null;

function openEditModal(code, name, allowedDaysCsv) {
    currentEditStudent = code;
    document.getElementById('editModalCode').textContent = code;
    document.getElementById('editStudentCode').value = code;
    document.getElementById('editFullName').value = name;

    // Pre-select the student's current allowed days in the multi-select
    var days = allowedDaysCsv.split(',').map(function(d){ return d.trim(); });
    var select = document.getElementById('editAllowedDays');
    for (var i = 0; i < select.options.length; i++) {
        select.options[i].selected = days.indexOf(select.options[i].value) !== -1;
    }

    document.getElementById('editModalError').style.display = 'none';
    document.getElementById('editModal').classList.add('show');
}

function closeEditModal() { document.getElementById('editModal').classList.remove('show'); }

function saveEditStudent() {
    var newCode = document.getElementById('editStudentCode').value.trim().toUpperCase();
    var fullName = document.getElementById('editFullName').value.trim();
    var select = document.getElementById('editAllowedDays');
    var selectedDays = Array.from(select.selectedOptions).map(function(o){ return o.value; });
    var err = document.getElementById('editModalError');

    if (!newCode) {
        err.textContent = 'Student code cannot be empty.';
        err.style.display = 'block';
        return;
    }
    if (!fullName) {
        err.textContent = 'Full name cannot be empty.';
        err.style.display = 'block';
        return;
    }
    if (selectedDays.length === 0) {
        err.textContent = 'Select at least one allowed day.';
        err.style.display = 'block';
        return;
    }
    err.style.display = 'none';

    fetch('update_student.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            original_code: currentEditStudent,
            student_code: newCode,
            full_name: fullName,
            allowed_days: selectedDays.join(',')
        })
    }).then(function(r){ return r.json(); }).then(function(data){
        if (data.success) {
            closeEditModal();
            if (newCode !== currentEditStudent) {
                // Student code changed — every button in this row (QR, PIN,
                // fingerprint, delete) has the old code baked into its
                // onclick handler, so reload for a clean, fully consistent state.
                showToast('Student code updated — refreshing...');
                setTimeout(function(){ window.location.reload(); }, 700);
            } else {
                updateStudentRow(currentEditStudent, fullName, selectedDays.join(','));
                showToast('Student updated successfully.');
            }
        } else {
            err.textContent = data.message || 'Could not save changes.';
            err.style.display = 'block';
        }
    }).catch(function(){
        err.textContent = 'Network error. Please try again.';
        err.style.display = 'block';
    });
}

// Update the Name and Allowed Days cells in the table instantly, no reload
function updateStudentRow(code, newName, newDaysCsv) {
    var rows = document.querySelectorAll('#studentTable tbody tr');
    rows.forEach(function(row) {
        var codeCell = row.cells[1];
        if (codeCell && codeCell.textContent.trim() === code) {
            row.cells[2].textContent = newName;      // Name column
            row.cells[3].textContent = newDaysCsv;    // Allowed Days column
            // Keep the Edit button's stored values in sync for next open
            var editBtn = row.querySelector('button.btn-warning');
            if (editBtn) {
                editBtn.setAttribute('onclick',
                    "openEditModal('" + code + "','" + newName.replace(/'/g,"\\'") + "','" + newDaysCsv + "')");
            }
        }
    });
}

function submitPIN() {
    var pin = document.getElementById('pinInput').value.trim();
    var err = document.getElementById('pinModalError');
    if (!/^\d{4}$/.test(pin)) {
        err.textContent = 'PIN must be exactly 4 digits (numbers only).';
        err.style.display = 'block';
        return;
    }
    fetch('update_pin.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({student_code: currentPINStudent, pin: pin, action: 'set'})
    }).then(function(r){ return r.json(); }).then(function(data){
        if (data.success) {
            closePINModal();
            // Update PIN status cell instantly without page reload
            updatePINCell(currentPINStudent, true);
            showToast('PIN saved successfully for ' + currentPINStudent);
        } else {
            err.textContent = data.message || 'Error saving PIN.';
            err.style.display = 'block';
        }
    });
}
function deletePIN() {
    if (!confirm('Are you sure you want to delete the PIN for ' + currentPINStudent + '?')) return;
    fetch('update_pin.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({student_code: currentPINStudent, action: 'delete'})
    }).then(function(r){ return r.json(); }).then(function(data){
        if (data.success) {
            closePINModal();
            // Update PIN status cell instantly without page reload
            updatePINCell(currentPINStudent, false);
            showToast('PIN deleted for ' + currentPINStudent);
        } else {
            alert('Error: ' + (data.message || 'Could not delete PIN.'));
        }
    });
}

// Update the PIN status cell in the student table instantly
function updatePINCell(code, isSet) {
    // Find all rows in the student table
    var rows = document.querySelectorAll('#studentTable tbody tr');
    rows.forEach(function(row) {
        var codeCell = row.cells[1];
        if (codeCell && codeCell.textContent.trim() === code) {
            // PIN status is in column index 4
            var pinCell = row.cells[4];
            if (pinCell) {
                if (isSet) {
                    pinCell.innerHTML = '<span style="color:green;font-weight:bold;">Set</span>';
                } else {
                    pinCell.innerHTML = '<span style="color:#aaa;">Not Set</span>';
                }
            }
            // Also update the Set PIN button text
            var pinBtn = row.querySelector('[data-pin="' + code + '"]');
            if (pinBtn) {
                if (isSet) {
                    pinBtn.style.background = '#28a745';
                    pinBtn.innerHTML = '<i class="fa fa-key"></i> PIN ✓';
                    setTimeout(function() {
                        pinBtn.style.background = '#6f42c1';
                        pinBtn.innerHTML = '<i class="fa fa-key"></i> Set PIN';
                    }, 3000);
                } else {
                    pinBtn.style.background = '#6f42c1';
                    pinBtn.innerHTML = '<i class="fa fa-key"></i> Set PIN';
                }
            }
        }
    });
}

// Delete a student's enrolled fingerprint/Face ID credential(s)
function deleteFingerprint(code, name) {
    if (!confirm('Remove the enrolled fingerprint/Face ID for ' + name + '? They will need to enroll again to use biometric attendance.')) return;
    fetch('delete_fingerprint.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({student_code: code})
    }).then(function(r){ return r.json(); }).then(function(data){
        if (data.success) {
            var cell = document.getElementById('fp-status-' + code);
            if (cell) {
                cell.innerHTML = '<span style="color:#aaa">Not Enrolled</span>';
            }
            showToast('Fingerprint removed for ' + code);
        } else {
            alert('Error: ' + (data.message || 'Could not remove fingerprint.'));
        }
    }).catch(function(){
        alert('Network error. Please try again.');
    });
}

// Toast notification (no page reload needed)
function showToast(message) {
    var existing = document.getElementById('toastMsg');
    if (existing) existing.remove();
    var toast = document.createElement('div');
    toast.id = 'toastMsg';
    toast.textContent = message;
    toast.style.cssText = 'position:fixed;bottom:30px;right:30px;background:#003366;color:white;'
        + 'padding:14px 22px;border-radius:10px;font-size:14px;font-weight:600;'
        + 'z-index:9999;box-shadow:0 6px 20px rgba(0,0,0,0.3);'
        + 'animation:fadeInUp 0.3s ease;';
    document.body.appendChild(toast);
    setTimeout(function() {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.4s';
        setTimeout(function() { toast.remove(); }, 400);
    }, 3000);
}
document.getElementById('pinModal').addEventListener('click', function(e){ if(e.target===this) closePINModal(); });
document.getElementById('editModal').addEventListener('click', function(e){ if(e.target===this) closeEditModal(); });
document.getElementById('pinInput').addEventListener('input', function(){ this.value = this.value.replace(/[^0-9]/g,''); });
document.getElementById('pinInput').addEventListener('keydown', function(e){ if(e.key==='Enter') submitPIN(); });

// Set active on load
document.getElementById('nav-dashboard').classList.add('active');
</script>
</body>
</html>
