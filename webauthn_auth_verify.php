<?php
// ============================================================
// webauthn_auth_verify.php
// Step 2 of fingerprint attendance: receives the browser's
// assertion response from navigator.credentials.get(), verifies
// the ECDSA signature against the student's stored public key,
// then marks attendance (same one-per-day rule as QR/manual).
// ============================================================
header('Content-Type: application/json');
session_start();
include 'db.php';
include 'webauthn_config.php';
include 'inc_webauthn_helpers.php';

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($_SESSION['webauthn_auth_challenge']) || !isset($_SESSION['webauthn_auth_student_code'])) {
    echo json_encode(["success" => false, "message" => "Session expired. Please try again."]);
    exit;
}
$student_code = $_SESSION['webauthn_auth_student_code'];
$expectedChallenge = $_SESSION['webauthn_auth_challenge'];

try {
    $clientDataJSON = b64url_decode($data['clientDataJSON']);
    $authenticatorData = b64url_decode($data['authenticatorData']);
    $signature = b64url_decode($data['signature']);
    $credentialId = $data['credentialId'];

    // ---- Verify clientDataJSON ----
    $clientData = json_decode($clientDataJSON, true);
    if ($clientData['type'] !== 'webauthn.get') {
        throw new Exception("Unexpected ceremony type.");
    }
    if (base64_encode(b64url_decode($clientData['challenge'])) !== $expectedChallenge) {
        throw new Exception("Challenge mismatch — possible replay attempt.");
    }
    if ($clientData['origin'] !== WEBAUTHN_ORIGIN) {
        throw new Exception("Origin mismatch.");
    }

    // ---- Look up the stored credential ----
    $credId_esc = mysqli_real_escape_string($conn, $credentialId);
    $result = mysqli_query($conn,
        "SELECT * FROM webauthn_credentials WHERE credential_id='$credId_esc' AND student_code='"
        . mysqli_real_escape_string($conn, $student_code) . "'");
    $cred = mysqli_fetch_assoc($result);
    if (!$cred) {
        throw new Exception("Unrecognized credential.");
    }

    // ---- Parse authenticatorData and verify RP ID / user verification ----
    $authData = parse_authenticator_data($authenticatorData);
    if ($authData['rpIdHash'] !== hash('sha256', WEBAUTHN_RP_ID, true)) {
        throw new Exception("RP ID hash mismatch.");
    }
    if (!$authData['userVerified']) {
        throw new Exception("Biometric verification was not performed.");
    }

    // ---- Verify the signature ----
    $signedData = $authenticatorData . hash('sha256', $clientDataJSON, true);
    $valid = verify_es256($cred['public_key_pem'], $signedData, $signature);
    if (!$valid) {
        throw new Exception("Signature verification failed.");
    }

    // ---- Anti-cloning check: signCount must strictly increase ----
    // (Real authenticators increment this every use; a cloned
    // authenticator would replay an old, lower count.)
    if ($authData['signCount'] > 0 && $authData['signCount'] <= $cred['sign_count']) {
        throw new Exception("Possible cloned authenticator detected. Please contact admin.");
    }
    mysqli_query($conn,
        "UPDATE webauthn_credentials SET sign_count=" . intval($authData['signCount']) .
        " WHERE id=" . intval($cred['id']));

    // ---- Signature is valid — now mark attendance ----
    $studentRow = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT * FROM students WHERE student_code='" . mysqli_real_escape_string($conn, $student_code) . "'"));
    if (!$studentRow) { throw new Exception("Student record not found."); }

    $date = date('Y-m-d');
    $time = date('H:i:s');
    $dayName = date('l'); // e.g. "Monday"
    $allowedDays = array_map('trim', explode(',', $studentRow['allowed_days']));
    $status = in_array($dayName, $allowedDays) ? 'ALLOWED' : 'DENIED';

    $full_name_esc = mysqli_real_escape_string($conn, $studentRow['full_name']);

    // One-attendance-per-day check (same rule as mark_attendance.php)
    $check = mysqli_query($conn,
        "SELECT id FROM attendance WHERE full_name='$full_name_esc' AND attendance_date='$date'");
    if (mysqli_num_rows($check) > 0) {
        echo json_encode([
            "success" => false,
            "duplicate" => true,
            "message" => $studentRow['full_name'] . " has already marked attendance today."
        ]);
        exit;
    }

    $student_code_esc = mysqli_real_escape_string($conn, $studentRow['student_code']);
    $allowed_days_esc = mysqli_real_escape_string($conn, $studentRow['allowed_days']);

    $sql = "INSERT INTO attendance
            (student_code, full_name, allowed_days, status, attendance_day, attendance_date, attendance_time, source)
            VALUES ('$student_code_esc', '$full_name_esc', '$allowed_days_esc', '$status', '$dayName', '$date', '$time', 'fingerprint')";

    unset($_SESSION['webauthn_auth_challenge']);
    unset($_SESSION['webauthn_auth_student_code']);

    if (mysqli_query($conn, $sql)) {
        echo json_encode([
            "success" => true,
            "status" => $status,
            "full_name" => $studentRow['full_name'],
            "message" => $status === 'ALLOWED'
                ? "Attendance marked successfully via fingerprint."
                : "Fingerprint verified, but today is not an allowed attendance day."
        ]);
    } else {
        throw new Exception("Database error: " . mysqli_error($conn));
    }

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
