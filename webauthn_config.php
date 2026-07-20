<?php
// ============================================================
// webauthn_config.php — WebAuthn Relying Party settings
// Auto-detects local (XAMPP) vs live (InfinityFree) and sets
// the Relying Party ID / origin to match. Browsers treat
// "localhost" as a secure context even over plain HTTP, so
// fingerprint/Face ID testing works locally too.
//
// If you ever move the LIVE site to a custom domain, update
// only the two values in the "else" branch below to match.
// ============================================================

$isLocal = in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1'])
    || strpos($_SERVER['SERVER_NAME'] ?? '', '.local') !== false;

define('WEBAUTHN_RP_NAME', 'MIST SIWES Attendance System');

if ($isLocal) {
    define('WEBAUTHN_RP_ID', 'localhost');
    define('WEBAUTHN_ORIGIN', 'http://localhost');
} else {
    define('WEBAUTHN_RP_ID', 'mist-attendance.rf.gd');
    define('WEBAUTHN_ORIGIN', 'https://mist-attendance.rf.gd');
}
?>
