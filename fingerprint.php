<?php
include 'db.php';
$studentsResult = mysqli_query($conn, "SELECT * FROM students ORDER BY student_code ASC");
$studentsList = [];
while ($r = mysqli_fetch_assoc($studentsResult)) $studentsList[] = $r;

$today = date('l');
$fullDate = date('F j, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Fingerprint Attendance — MIST</title>
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#003366">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
<style>
*{ margin:0; padding:0; box-sizing:border-box; font-family:Arial,Helvetica,sans-serif; }
body{
    background:linear-gradient(135deg,#001f4d,#003366,#00509e);
    min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px;
}
.card{
    background:white; border-radius:16px; padding:40px 34px;
    width:100%; max-width:440px; box-shadow:0 20px 50px rgba(0,0,0,0.3); text-align:center;
}
.icon-wrap{
    width:70px; height:70px; border-radius:50%;
    background:linear-gradient(135deg,#003366,#00509e);
    display:flex; align-items:center; justify-content:center;
    margin:0 auto 16px; font-size:30px; color:white;
}
h2{ color:#003366; font-size:19px; margin-bottom:6px; }
.subtitle{ color:#888; font-size:13px; margin-bottom:6px; }
.date-badge{
    display:inline-block; background:#f0f4ff; color:#003366;
    padding:6px 16px; border-radius:20px; font-size:12px; font-weight:600;
    margin:14px 0 20px; border:1px solid #ccd9f0;
}
label{ display:block; font-size:13px; color:#444; font-weight:600; margin-bottom:6px; text-align:left; }
select{
    width:100%; padding:12px 14px; border:1.5px solid #ddd; border-radius:8px; font-size:14px;
}
select:focus{ outline:none; border-color:#003366; }
.fingerprint-icon{
    font-size:64px; color:#00509e; margin:24px 0;
    transition:transform 0.2s;
}
.fingerprint-icon.scanning{ animation:pulse 1s infinite; }
@keyframes pulse{ 0%,100%{ transform:scale(1); opacity:1; } 50%{ transform:scale(1.08); opacity:0.7; } }
button{
    width:100%; padding:14px; margin-top:8px; border:none; border-radius:8px;
    background:linear-gradient(135deg,#003366,#00509e); color:white;
    font-size:15px; font-weight:700; cursor:pointer;
}
button:hover{ opacity:0.92; }
button:disabled{ opacity:0.5; cursor:not-allowed; }
.status-msg{
    margin-top:16px; padding:12px 14px; border-radius:8px; font-size:13px; text-align:center; display:none;
}
.status-msg.error{ background:#f8d7da; color:#721c24; display:block; }
.status-msg.success{ background:#d4edda; color:#155724; display:block; }
.status-msg.info{ background:#e7f1fc; color:#0c5aa6; display:block; }
.status-msg.warn{ background:#fff3cd; color:#856404; display:block; }
.links-row{ margin-top:20px; display:flex; justify-content:space-between; font-size:13px; }
.links-row a{ color:#003366; text-decoration:none; }
.links-row a:hover{ text-decoration:underline; }
</style>
</head>
<body>
<div class="card">
    <div class="icon-wrap"><i class="fa fa-fingerprint"></i></div>
    <h2>Fingerprint Attendance</h2>
    <p class="subtitle">Select your name, then use your fingerprint or Face ID.</p>
    <div class="date-badge"><?= htmlspecialchars($today . ', ' . $fullDate) ?></div>

    <label>Select Your Name</label>
    <select id="studentSelect">
        <option value="">-- Select Student --</option>
        <?php foreach ($studentsList as $s): ?>
        <option value="<?= htmlspecialchars($s['student_code']) ?>">
            <?= htmlspecialchars($s['student_code']) ?> — <?= htmlspecialchars($s['full_name']) ?>
        </option>
        <?php endforeach; ?>
    </select>

    <div class="fingerprint-icon" id="fpIcon"><i class="fa fa-fingerprint"></i></div>

    <button onclick="signAttendance()" id="signBtn"><i class="fa fa-shield-halved"></i> Sign Attendance</button>
    <div class="status-msg" id="statusMsg"></div>

    <div class="links-row">
        <a href="scanner.php"><i class="fa fa-qrcode"></i> Use QR / Manual instead</a>
        <a href="fingerprint_enroll.php"><i class="fa fa-user-plus"></i> Enroll a fingerprint</a>
    </div>
</div>

<script>
function showStatus(msg, type){
    const el = document.getElementById('statusMsg');
    el.textContent = msg;
    el.className = 'status-msg ' + type;
}

function b64urlToBuffer(b64url){
    const pad = '='.repeat((4 - b64url.length % 4) % 4);
    const base64 = (b64url + pad).replace(/-/g,'+').replace(/_/g,'/');
    const raw = atob(base64);
    const buf = new Uint8Array(raw.length);
    for(let i=0;i<raw.length;i++) buf[i] = raw.charCodeAt(i);
    return buf.buffer;
}
function bufferToB64url(buf){
    const bytes = new Uint8Array(buf);
    let str = '';
    for(let i=0;i<bytes.length;i++) str += String.fromCharCode(bytes[i]);
    return btoa(str).replace(/\+/g,'-').replace(/\//g,'_').replace(/=+$/,'');
}

async function signAttendance(){
    const studentCode = document.getElementById('studentSelect').value;
    if(!studentCode){ showStatus('Please select your name first.', 'error'); return; }

    if(!('credentials' in navigator) || !window.PublicKeyCredential){
        showStatus('This browser/device does not support fingerprint or Face ID login. Use QR or manual attendance instead.', 'error');
        return;
    }

    document.getElementById('signBtn').disabled = true;
    document.getElementById('fpIcon').classList.add('scanning');
    showStatus('Follow your device\'s prompt to scan your fingerprint or face...', 'info');

    try{
        const optRes = await fetch('webauthn_auth_options.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ student_code: studentCode })
        });
        const optData = await optRes.json();
        if(!optData.success){
            showStatus(optData.message + ' — try "Enroll a fingerprint" below, or use QR/manual.', 'warn');
            resetUI();
            return;
        }

        const options = optData.options;
        options.challenge = b64urlToBuffer(options.challenge);
        options.allowCredentials = options.allowCredentials.map(c => ({
            ...c, id: b64urlToBuffer(c.id)
        }));

        const assertion = await navigator.credentials.get({ publicKey: options });

        const payload = {
            credentialId: bufferToB64url(assertion.rawId),
            clientDataJSON: bufferToB64url(assertion.response.clientDataJSON),
            authenticatorData: bufferToB64url(assertion.response.authenticatorData),
            signature: bufferToB64url(assertion.response.signature),
        };

        const verifyRes = await fetch('webauthn_auth_verify.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify(payload)
        });
        const verifyData = await verifyRes.json();

        if(verifyData.success){
            showStatus((verifyData.status === 'ALLOWED' ? '✓ ' : '⚠ ') + verifyData.message, verifyData.status === 'ALLOWED' ? 'success' : 'warn');
        } else if(verifyData.duplicate){
            showStatus(verifyData.message, 'warn');
        } else {
            showStatus(verifyData.message || 'Verification failed.', 'error');
        }
    } catch(err){
        showStatus('Fingerprint scan was cancelled or failed: ' + err.message, 'error');
    }
    resetUI();
}

function resetUI(){
    document.getElementById('signBtn').disabled = false;
    document.getElementById('fpIcon').classList.remove('scanning');
}
</script>
</body>
</html>
