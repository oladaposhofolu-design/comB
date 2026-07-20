<?php
// ============================================================
// webauthn_register_options.php
// Step 1 of fingerprint enrollment: generates a random challenge
// and the options the browser needs to call
// navigator.credentials.create(). The student must already have
// proven their identity with their PIN (checked via session flag
// set by fingerprint_enroll.php) before this will respond.
// ============================================================
header('Content-Type: application/json');
session_start();
include 'db.php';
include 'webauthn_config.php';
include 'inc_webauthn_helpers.php';

$data = json_decode(file_get_contents("php://input"), true);
$student_code = mysqli_real_escape_string($conn, $data['student_code'] ?? '');

// Require that this student was just PIN-verified in this session
if (!isset($_SESSION['pin_verified_for']) || $_SESSION['pin_verified_for'] !== $student_code) {
    echo json_encode(["success" => false, "message" => "Please verify your PIN first."]);
    exit;
}

$result = mysqli_query($conn, "SELECT full_name FROM students WHERE student_code='$student_code'");
$student = mysqli_fetch_assoc($result);
if (!$student) {
    echo json_encode(["success" => false, "message" => "Student not found."]);
    exit;
}

// Generate a fresh random challenge and stash it in the session
// so webauthn_register_verify.php can confirm the browser's
// response corresponds to this exact challenge (prevents replay).
$challenge = random_bytes(32);
$_SESSION['webauthn_challenge'] = base64_encode($challenge);
$_SESSION['webauthn_student_code'] = $student_code;

// Existing credentials for this student, so the device doesn't
// register the same authenticator twice.
$existing = mysqli_query($conn,
    "SELECT credential_id FROM webauthn_credentials WHERE student_code='$student_code'");
$excludeCredentials = [];
while ($row = mysqli_fetch_assoc($existing)) {
    $excludeCredentials[] = [
        "type" => "public-key",
        "id"   => $row['credential_id'], // already base64url from storage
    ];
}

echo json_encode([
    "success" => true,
    "options" => [
        "challenge" => b64url_encode($challenge),
        "rp" => [
            "name" => WEBAUTHN_RP_NAME,
            "id"   => WEBAUTHN_RP_ID,
        ],
        "user" => [
            "id"   => b64url_encode($student_code), // opaque handle, not shown to user
            "name" => $student_code,
            "displayName" => $student['full_name'],
        ],
        "pubKeyCredParams" => [
            ["type" => "public-key", "alg" => -7], // ES256
        ],
        "authenticatorSelection" => [
            "authenticatorAttachment" => "platform",   // built-in sensor (fingerprint/Face ID), not a USB key
            "userVerification" => "required",           // must actually use biometric, not just "device present"
            "residentKey" => "preferred",
        ],
        "timeout" => 60000,
        "attestation" => "none",
        "excludeCredentials" => $excludeCredentials,
    ],
]);
?>
