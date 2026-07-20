<?php
// ============================================================
// webauthn_auth_options.php
// Step 1 of fingerprint attendance: generates a challenge and
// lists the student's enrolled credential(s) so the browser
// knows which fingerprint/Face ID to prompt for.
// ============================================================
header('Content-Type: application/json');
session_start();
include 'db.php';
include 'webauthn_config.php';
include 'inc_webauthn_helpers.php';

$data = json_decode(file_get_contents("php://input"), true);
$student_code = mysqli_real_escape_string($conn, $data['student_code'] ?? '');

if (!$student_code) {
    echo json_encode(["success" => false, "message" => "Student code missing."]);
    exit;
}

$result = mysqli_query($conn,
    "SELECT credential_id FROM webauthn_credentials WHERE student_code='$student_code'");

$allowCredentials = [];
while ($row = mysqli_fetch_assoc($result)) {
    $allowCredentials[] = [
        "type" => "public-key",
        "id"   => $row['credential_id'],
    ];
}

if (empty($allowCredentials)) {
    echo json_encode(["success" => false, "message" => "No fingerprint enrolled for this student yet."]);
    exit;
}

$challenge = random_bytes(32);
$_SESSION['webauthn_auth_challenge'] = base64_encode($challenge);
$_SESSION['webauthn_auth_student_code'] = $student_code;

echo json_encode([
    "success" => true,
    "options" => [
        "challenge" => b64url_encode($challenge),
        "rpId" => WEBAUTHN_RP_ID,
        "allowCredentials" => $allowCredentials,
        "userVerification" => "required",
        "timeout" => 60000,
    ],
]);
?>
