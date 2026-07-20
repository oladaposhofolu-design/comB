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
<style>
*{ margin:0; padding:0; box-sizing:border-box; }
    body{
    font-family:'Segoe UI',Arial,sans-serif;
    background-image:url("picture1.jpg");
    background-size:cover;
    background-position:center;
    background-repeat:no-repeat;
    background-attachment:scroll;
    animation:bgSlide 20s infinite;
    min-height:100vh;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
}
@keyframes bgSlide{
    0%, 22%{
        background-image:url("bg1.jpg");
    }
    25%, 47%{
        background-image:url("image2.jpg");
    }
    50%, 72%{
        background-image:url("image3.jpg");
    }
    75%, 100%{
        background-image:url("image9.jpg");
    }
}
.welcome-card{
    background:rgba(255,255,255,0.97);
    border-radius:20px; padding:60px 50px;
    text-align:center; max-width:520px; width:90%;
    box-shadow:0 25px 60px rgba(0,0,0,0.35);
}
.logo-wrap{
    width:170px; height:170px; border-radius:50%;
    background:linear-gradient(135deg,#003366,#00509e);
    margin:0 auto 24px;
    display:flex; align-items:center; justify-content:center;
    box-shadow:0 8px 25px rgba(0,51,102,0.35); overflow:hidden;
}
.logo-wrap img{ width:162px; height:162px; object-fit:contain; border-radius:50%; }
.logo-text{ font-size:32px; font-weight:900; color:white; letter-spacing:2px; line-height:1; }
.logo-text span{ display:block; font-size:11px; font-weight:400; letter-spacing:3px; margin-top:4px; opacity:0.85; }
.ministry-name{ font-size:13px; font-weight:700; color:#003366; text-transform:uppercase; letter-spacing:1.5px; line-height:1.6; margin-bottom:6px; }
.system-title{ font-size:18px; font-weight:800; color:#00509e; margin-bottom:8px; }
.divider{ width:60px; height:3px; background:linear-gradient(90deg,#003366,#00509e); margin:18px auto; border-radius:2px; }
.welcome-text{ font-size:14px; color:#666; margin-bottom:36px; line-height:1.7; }
.date-badge{ display:inline-block; background:#f0f4ff; color:#003366; padding:6px 16px; border-radius:20px; font-size:12px; font-weight:600; margin-bottom:20px; border:1px solid #ccd9f0; }
.btn-attend{
    display:block; width:100%; padding:16px;
    background:linear-gradient(135deg,#003366,#00509e);
    color:white; border:none; border-radius:10px;
    font-size:16px; font-weight:700; cursor:pointer;
    text-decoration:none; letter-spacing:0.5px;
    transition:all 0.3s ease;
    box-shadow:0 5px 20px rgba(0,51,102,0.3); margin-bottom:14px;
}
.btn-attend:hover{ transform:translateY(-2px); box-shadow:0 8px 28px rgba(0,51,102,0.45); }
.btn-admin{
    display:block; width:100%; padding:13px;
    background:transparent; color:#003366;
    border:2px solid #003366; border-radius:10px;
    font-size:14px; font-weight:600; cursor:pointer;
    text-decoration:none; transition:all 0.3s ease;
}
.btn-admin:hover{ background:#003366; color:white; }
.footer-note{ margin-top:28px; font-size:11px; color:#aaa; }

/* ── SIGN-IN METHOD MODAL ── */
.signin-option{
    display:flex; align-items:center; gap:16px;
    width:100%; padding:16px 18px; margin-bottom:12px;
    border:2px solid #eee; border-radius:12px; background:white;
    text-decoration:none; text-align:left; cursor:pointer;
    transition:all 0.2s ease;
}
.signin-option:hover{ border-color:#00509e; background:#f7faff; transform:translateY(-1px); }
.signin-option .so-icon{
    width:48px; height:48px; border-radius:50%; flex-shrink:0;
    background:linear-gradient(135deg,#003366,#00509e);
    display:flex; align-items:center; justify-content:center;
    color:white; font-size:20px;
}
.signin-option .so-text h4{ color:#003366; font-size:15px; margin-bottom:2px; }
.signin-option .so-text p{ color:#888; font-size:12px; }

/* ── SECRET KEY MODAL ── */
.sk-overlay{
    display:none; position:fixed; inset:0;
    background:rgba(0,0,0,0.65); z-index:9999;
    align-items:center; justify-content:center;
}
.sk-overlay.show{ display:flex; }
.sk-box{
    background:white; border-radius:18px; padding:40px 36px;
    text-align:center; max-width:400px; width:92%;
    box-shadow:0 24px 60px rgba(0,0,0,0.4);
    animation:popIn 0.25s ease;
}
@keyframes popIn{
    from{ transform:scale(0.85); opacity:0; }
    to{   transform:scale(1);    opacity:1; }
}
.sk-icon{
    width:68px; height:68px; border-radius:50%;
    background:linear-gradient(135deg,#003366,#00509e);
    display:flex; align-items:center; justify-content:center;
    margin:0 auto 16px; font-size:28px; color:white;
}
.sk-box h3{ color:#003366; font-size:20px; margin-bottom:6px; }
.sk-box p{ color:#888; font-size:13px; margin-bottom:22px; }
.sk-input-wrap{ position:relative; margin-bottom:12px; }
.sk-input-wrap input{
    width:100%; padding:14px 44px 14px 16px;
    border:2px solid #ddd; border-radius:10px;
    font-size:16px; letter-spacing:3px; text-align:center;
    transition:border 0.2s; outline:none;
}
.sk-input-wrap input:focus{ border-color:#003366; }
.sk-toggle{
    position:absolute; right:14px; top:50%;
    transform:translateY(-50%);
    background:none; border:none; cursor:pointer;
    color:#999; font-size:16px; padding:0; width:auto; margin:0;
}
.sk-error{
    background:#f8d7da; color:#721c24;
    padding:9px 14px; border-radius:8px;
    font-size:13px; margin-bottom:12px; display:none;
}
.sk-attempts{ font-size:12px; color:#999; margin-bottom:12px; }
.sk-btn{
    width:100%; padding:14px; border:none; border-radius:10px;
    background:linear-gradient(135deg,#003366,#00509e);
    color:white; font-size:15px; font-weight:700;
    cursor:pointer; margin-bottom:10px; transition:opacity 0.2s;
}
.sk-btn:hover{ opacity:0.9; }
.sk-cancel{
    width:100%; padding:11px; border:1.5px solid #ddd;
    border-radius:10px; background:white; color:#666;
    font-size:14px; cursor:pointer; transition:all 0.2s;
}
.sk-cancel:hover{ background:#f8f9fa; }
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

<div class="welcome-card">
    <div class="logo-wrap">
        <img src="logo.png" alt="MIST Logo"
            onerror="this.style.display='none';document.getElementById('logoText').style.display='block'"/>
        <div class="logo-text" id="logoText" style="display:none">
            MIST<span>NIGERIA</span>
        </div>
    </div>

    <div class="ministry-name">Ministry of Innovation,<br>Science &amp; Technology</div>
    <div class="system-title">SIWES Attendance System</div>
    <div class="divider"></div>
    <div class="date-badge" id="landingDate"></div>

    <p class="welcome-text">
        Welcome to the MIST SIWES Attendance Management Portal.<br>
        Please proceed to sign your attendance below.
    </p>

    <a href="#" class="btn-attend" onclick="openSignInModal(event)">
        <i class="fa fa-qrcode"></i>&nbsp;&nbsp; Proceed to Sign Attendance
    </a>

    <a href="#" class="btn-admin" onclick="openSecretKey(event)">
        <i class="fa fa-lock"></i>&nbsp;&nbsp; Admin Login
    </a>

    <div class="footer-note">
        &copy; 2026 Ministry of Innovation, Science &amp; Technology &mdash; Nigeria
    </div>
</div>

<!-- SECRET KEY MODAL -->
<div class="sk-overlay" id="skOverlay">
    <div class="sk-box" id="skBox">
        <div class="sk-icon"><i class="fa fa-shield-halved"></i></div>
        <h3>Admin Access</h3>
        <p>Enter the secret key to proceed to the admin login page.</p>

        <div class="sk-input-wrap">
            <input type="password" id="skInput" maxlength="20"
                placeholder="Enter secret key"
                onkeydown="if(event.key==='Enter') verifyKey()"/>
            <button class="sk-toggle" onclick="toggleShow()" id="skToggle">
                <i class="fa fa-eye" id="skEyeIcon"></i>
            </button>
        </div>

        <div class="sk-error" id="skError"></div>
        <div class="sk-attempts" id="skAttempts"></div>

        <button class="sk-btn" onclick="verifyKey()">
            <i class="fa fa-arrow-right"></i>&nbsp; Proceed
        </button>
        <button class="sk-cancel" onclick="closeSecretKey()">
            <i class="fa fa-times"></i>&nbsp; Cancel
        </button>
    </div>
</div>

<!-- SIGN-IN METHOD MODAL -->
<div class="sk-overlay" id="signinOverlay">
    <div class="sk-box" id="signinBox">
        <div class="sk-icon"><i class="fa fa-clipboard-check"></i></div>
        <h3>How would you like to sign in?</h3>
        <p>Choose a method to record your attendance for today.</p>

        <a href="scanner.php" class="signin-option">
            <div class="so-icon"><i class="fa fa-qrcode"></i></div>
            <div class="so-text">
                <h4>QR Code / Manual Attendance</h4>
                <p>Scan your QR code or enter your student PIN</p>
            </div>
        </a>

        <a href="fingerprint.php" class="signin-option">
            <div class="so-icon"><i class="fa fa-fingerprint"></i></div>
            <div class="so-text">
                <h4>Fingerprint / Face ID</h4>
                <p>Use your device's biometric sensor</p>
            </div>
        </a>

        <button class="sk-cancel" onclick="closeSignInModal()">
            <i class="fa fa-times"></i>&nbsp; Cancel
        </button>
    </div>
</div>

<script>
// ── Set today's date ──
const d = new Date();
document.getElementById("landingDate").textContent =
    d.toLocaleDateString('en-US',{weekday:'long',year:'numeric',month:'long',day:'numeric'});

// ── SECRET KEY CONFIG ──
// Change this to your desired secret key
const SECRET_KEY = "MIST2026";
let skAttempts = 0;
const MAX_ATTEMPTS = 3;
let skLocked = false;

function openSignInModal(e){
    e.preventDefault();
    document.getElementById('signinOverlay').classList.add('show');
}

function closeSignInModal(){
    document.getElementById('signinOverlay').classList.remove('show');
}

function openSecretKey(e){
    e.preventDefault();
    if(skLocked){
        alert('Too many failed attempts. Please try again later.');
        return;
    }
    document.getElementById('skInput').value = '';
    document.getElementById('skError').style.display = 'none';
    document.getElementById('skAttempts').textContent = '';
    document.getElementById('skOverlay').classList.add('show');
    setTimeout(function(){ document.getElementById('skInput').focus(); }, 150);
}

function closeSecretKey(){
    document.getElementById('skOverlay').classList.remove('show');
}

function toggleShow(){
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

    if(val === SECRET_KEY){
        // Correct — go to login page
        closeSecretKey();
        window.location.href = 'login.php';
    } else {
        skAttempts++;
        // Shake animation
        var box = document.getElementById('skBox');
        box.style.animation = 'none';
        void box.offsetWidth;
        box.style.animation = 'shake 0.4s ease';

        if(skAttempts >= MAX_ATTEMPTS){
            skLocked = true;
            err.textContent = 'Too many failed attempts. Access locked.';
            err.style.display = 'block';
            att.textContent = '';
            setTimeout(function(){
                // Unlock after 1 minute
                skAttempts = 0;
                skLocked = false;
                closeSecretKey();
            }, 60000);
        } else {
            var left = MAX_ATTEMPTS - skAttempts;
            err.textContent = 'Incorrect secret key.';
            err.style.display = 'block';
            att.textContent = left + ' attempt(s) remaining.';
        }
        document.getElementById('skInput').value = '';
        document.getElementById('skInput').focus();
    }
}

// Close on overlay click
document.getElementById('skOverlay').addEventListener('click', function(e){
    if(e.target === this) closeSecretKey();
});
document.getElementById('signinOverlay').addEventListener('click', function(e){
    if(e.target === this) closeSignInModal();
});

// Shake animation style
var style = document.createElement('style');
style.textContent = '@keyframes shake{ 0%,100%{transform:translateX(0)} 20%{transform:translateX(-10px)} 40%{transform:translateX(10px)} 60%{transform:translateX(-7px)} 80%{transform:translateX(7px)} }';
document.head.appendChild(style);
</script>
</body>
</html>
