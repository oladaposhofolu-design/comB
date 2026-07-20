<?php
include 'db.php';
$studentsResult = mysqli_query($conn, "SELECT * FROM students ORDER BY student_code ASC");
$studentsList = [];
while ($r = mysqli_fetch_assoc($studentsResult)) $studentsList[] = $r;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Enroll Fingerprint — MIST Attendance</title>
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
    width:100%; max-width:440px; box-shadow:0 20px 50px rgba(0,0,0,0.3);
}
.icon-wrap{
    width:64px; height:64px; border-radius:50%;
    background:linear-gradient(135deg,#003366,#00509e);
    display:flex; align-items:center; justify-content:center;
    margin:0 auto 16px; font-size:26px; color:white;
}
h2{ color:#003366; text-align:center; font-size:19px; margin-bottom:6px; }
.subtitle{ color:#888; text-align:center; font-size:13px; margin-bottom:26px; }
label{ display:block; font-size:13px; color:#444; font-weight:600; margin-bottom:6px; margin-top:16px; }
select, input{
    width:100%; padding:12px 14px; border:1.5px solid #ddd; border-radius:8px; font-size:14px;
}
select:focus, input:focus{ outline:none; border-color:#003366; }
button{
    width:100%; padding:14px; margin-top:22px; border:none; border-radius:8px;
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
.step{ display:none; }
.step.active{ display:block; }
.back-link{ display:block; text-align:center; margin-top:20px; font-size:13px; color:#003366; text-decoration:none; }
.back-link:hover{ text-decoration:underline; }
.fingerprint-icon{ font-size:56px; color:#00509e; text-align:center; margin:20px 0; }
</style>
</head>
<body>
<div class="card">

    <!-- STEP 1: Select student + verify PIN -->
    <div class="step active" id="step1">
        <div class="icon-wrap"><i class="fa fa-fingerprint"></i></div>
        <h2>Enroll Your Fingerprint</h2>
        <p class="subtitle">Verify your identity with your PIN first, then register your fingerprint or Face ID for faster attendance.</p>

        <label>Select Your Name</label>
        <select id="studentSelect">
            <option value="">-- Select Student --</option>
            <?php foreach ($studentsList as $s): ?>
            <option value="<?= htmlspecialchars($s['student_code']) ?>">
                <?= htmlspecialchars($s['student_code']) ?> — <?= htmlspecialchars($s['full_name']) ?>
            </option>
            <?php endforeach; ?>
        </select>

        <label>Enter Your 4-Digit PIN</label>
        <input type="password" id="pinInput" maxlength="4" inputmode="numeric" placeholder="••••"/>

        <button onclick="verifyPin()" id="verifyBtn"><i class="fa fa-unlock"></i> Verify PIN</button>
        <div class="status-msg" id="step1Status"></div>
    </div>

    <!-- STEP 2: Register fingerprint -->
    <div class="step" id="step2">
        <div class="icon-wrap"><i class="fa fa-fingerprint"></i></div>
        <h2>Register Your Device</h2>
        <p class="subtitle">Identity confirmed. Now use your device's fingerprint sensor or Face ID to complete enrollment.</p>
        <div class="fingerprint-icon"><i class="fa fa-fingerprint"></i></div>
        <button onclick="registerFingerprint()" id="registerBtn"><i class="fa fa-shield-halved"></i> Register Fingerprint / Face ID</button>
        <div class="status-msg" id="step2Status"></div>
    </div>

    <!-- STEP 3: Success -->
    <div class="step" id="step3">
        <div class="icon-wrap" style="background:linear-gradient(135deg,#1e7e34,#28a745);"><i class="fa fa-check"></i></div>
        <h2>Enrollment Complete</h2>
        <p class="subtitle">You can now sign attendance instantly using your fingerprint or Face ID.</p>
        <button onclick="window.location.href='index.php'"><i class="fa fa-home"></i> Back to Home</button>
    </div>

    <a href="index.php" class="back-link"><i class="fa fa-arrow-left"></i> Cancel and go back</a>
</div>

<script>
let verifiedStudentCode = null;

function showStatus(elId, msg, type){
    const el = document.getElementById(elId);
    el.textContent = msg;
    el.className = 'status-msg ' + type;
}

function goToStep(n){
    document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
    document.getElementById('step' + n).classList.add('active');
}

async function verifyPin(){
    const studentCode = document.getElementById('studentSelect').value;
    const pin = document.getElementById('pinInput').value.trim();

    if(!studentCode){ showStatus('step1Status', 'Please select your name.', 'error'); return; }
    if(pin.length !== 4){ showStatus('step1Status', 'PIN must be exactly 4 digits.', 'error'); return; }

    document.getElementById('verifyBtn').disabled = true;
    try{
        const res = await fetch('mark_pin_verified.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ student_code: studentCode, pin: pin })
        });
        const data = await res.json();
        if(data.success){
            verifiedStudentCode = studentCode;
            showStatus('step1Status', '', '');
            goToStep(2);
        } else {
            showStatus('step1Status', data.message || 'Incorrect PIN.', 'error');
        }
    } catch(err){
        showStatus('step1Status', 'Network error. Please try again.', 'error');
    }
    document.getElementById('verifyBtn').disabled = false;
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

async function registerFingerprint(){
    if(!('credentials' in navigator) || !window.PublicKeyCredential){
        showStatus('step2Status', 'This browser/device does not support fingerprint or Face ID login.', 'error');
        return;
    }

    document.getElementById('registerBtn').disabled = true;
    showStatus('step2Status', 'Follow your device\'s prompt to scan your fingerprint or face...', 'info');

    try{
        const optRes = await fetch('webauthn_register_options.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ student_code: verifiedStudentCode })
        });
        const optData = await optRes.json();
        if(!optData.success){
            showStatus('step2Status', optData.message, 'error');
            document.getElementById('registerBtn').disabled = false;
            return;
        }

        const options = optData.options;
        options.challenge = b64urlToBuffer(options.challenge);
        options.user.id = b64urlToBuffer(options.user.id);
        if(options.excludeCredentials){
            options.excludeCredentials = options.excludeCredentials.map(c => ({
                ...c, id: b64urlToBuffer(c.id)
            }));
        }

        const credential = await navigator.credentials.create({ publicKey: options });

        const payload = {
            credentialId: bufferToB64url(credential.rawId),
            clientDataJSON: bufferToB64url(credential.response.clientDataJSON),
            attestationObject: bufferToB64url(credential.response.attestationObject),
        };

        const verifyRes = await fetch('webauthn_register_verify.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify(payload)
        });
        const verifyData = await verifyRes.json();

        if(verifyData.success){
            goToStep(3);
        } else {
            showStatus('step2Status', verifyData.message || 'Enrollment failed.', 'error');
        }
    } catch(err){
        showStatus('step2Status', 'Fingerprint registration was cancelled or failed: ' + err.message, 'error');
    }
    document.getElementById('registerBtn').disabled = false;
}
</script>
</body>
</html>
