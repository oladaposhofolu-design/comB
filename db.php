<?php
// ============================================================
// db.php — Database connection
// Auto-detects whether this is running on your local XAMPP
// server or the live InfinityFree server, and connects with
// the matching credentials — no manual editing needed when
// moving between the two.
// ============================================================

$isLocal = in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1'])
    || strpos($_SERVER['SERVER_NAME'] ?? '', '.local') !== false;

if ($isLocal) {
    // ---- LOCAL (XAMPP) SETTINGS ----
    // Default XAMPP MySQL: root user, no password. Change dbname
    // here if you named your local database something else.
    $host   = "localhost";
    $user   = "root";
    $pass   = "";
    $dbname = "mist_attendance";
} else {
    // ---- LIVE (InfinityFree) SETTINGS ----
    $host   = "sql101.infinityfree.com";
    $user   = "if0_42306968";
    $pass   = "Adetunji2004";
    $dbname = "if0_42306968_mist_attendance";
}

$conn = mysqli_connect($host, $user, $pass, $dbname);
if(!$conn){
    die(json_encode(["error" => "Connection failed: " . mysqli_connect_error()]));
}
mysqli_set_charset($conn, "utf8");
?>
