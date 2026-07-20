<?php
session_start();

if(isset($_SESSION['admin'])){
    header("Location: dashboard.php");
    exit;
}

include 'db.php';
$error = "";

if(isset($_POST['login'])){
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = md5($_POST['password']);

    $query = mysqli_query($conn,
        "SELECT * FROM admins WHERE username='$username' AND password='$password'");

    if(mysqli_num_rows($query) > 0){
        $_SESSION['admin'] = $username;
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Invalid username or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Admin Login — MIST</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
<style>
*{ margin:0; padding:0; box-sizing:border-box; }
body{
    font-family:'Segoe UI',Arial,sans-serif;
    background:linear-gradient(135deg,#001f4d,#003366,#00509e);
    min-height:100vh;
    display:flex; align-items:center; justify-content:center;
}
.login-card{
    background:white;
    border-radius:16px;
    padding:50px 40px;
    width:100%;
    max-width:420px;
    box-shadow:0 20px 50px rgba(0,0,0,0.3);
}
.login-logo{
    text-align:center;
    margin-bottom:30px;
}
.login-logo .icon-wrap{
    width:70px; height:70px;
    border-radius:50%;
    background:linear-gradient(135deg,#003366,#00509e);
    display:flex; align-items:center; justify-content:center;
    margin:0 auto 14px;
}
.login-logo .icon-wrap i{ color:white; font-size:28px; }
.login-logo h2{ color:#003366; font-size:20px; margin-bottom:4px; }
.login-logo p{ color:#888; font-size:13px; }
.form-group{ margin-bottom:18px; }
.form-group label{ display:block; font-size:13px; color:#444; font-weight:600; margin-bottom:6px; }
.form-group input{
    width:100%; padding:12px 14px;
    border:1.5px solid #ddd; border-radius:8px;
    font-size:14px; transition:border 0.2s;
}
.form-group input:focus{ outline:none; border-color:#003366; }
.btn-login{
    width:100%; padding:14px;
    background:linear-gradient(135deg,#003366,#00509e);
    color:white; border:none; border-radius:8px;
    font-size:15px; font-weight:700; cursor:pointer;
}
.btn-login:hover{ opacity:0.9; }
.error-msg{
    background:#f8d7da; color:#721c24;
    padding:10px 14px; border-radius:6px;
    margin-bottom:18px; font-size:13px;
    text-align:center;
}
.back-link{
    display:block; text-align:center;
    margin-top:18px; font-size:13px;
    color:#003366; text-decoration:none;
}
.back-link:hover{ text-decoration:underline; }
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
<div class="login-card">
    <div class="login-logo">
        <div class="icon-wrap"><i class="fa fa-shield-halved"></i></div>
        <h2>Admin Login</h2>
        <p>MISAT Attendance System</p>
    </div>

    <?php if($error): ?>
    <div class="error-msg"><i class="fa fa-exclamation-circle"></i> <?= $error ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label><i class="fa fa-user"></i> Username</label>
            <input type="text" name="username" placeholder="Enter username" required autocomplete="off"/>
        </div>
        <div class="form-group">
            <label><i class="fa fa-lock"></i> Password</label>
            <input type="password" name="password" placeholder="Enter password" required/>
        </div>
        <button type="submit" name="login" class="btn-login">
            <i class="fa fa-right-to-bracket"></i> Login
        </button>
    </form>

    <a href="index.php" class="back-link">
        <i class="fa fa-arrow-left"></i> Back to Home
    </a>
</div>
</body>
</html>