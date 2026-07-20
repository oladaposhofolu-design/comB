<?php
// ============================================================
// mark_pin_verified.php
// Independently re-checks the student's PIN against the
// database (does not just trust that verify_pin.php was called
// first — a request could otherwise be forged) and, only if
// correct, sets a short-lived session flag that
// webauthn_register_options.php requires before allowing a new
// fingerprint to be enrolled for that student.
// ============================================================
header('Content-Type: application/json');
session_start();
include 'db.php';

$data = json_decode(file_get_contents("php://input"), true);
$student_code = mysqli_real_escape_string($conn, $data['student_code'] ?? '');
$pin = mysqli_real_escape_string($conn, $data['pin'] ?? '');

if (!$student_code || !$pin) {
    echo json_encode(["success" => false, "message" => "Missing data."]);
    exit;
}

$result = mysqli_query($conn, "SELECT pin FROM students WHERE student_code='$student_code'");
$student = mysqli_fetch_assoc($result);

if (!$student || $student['pin'] === null || $student['pin'] !== $pin) {
    echo json_encode(["success" => false, "message" => "PIN verification failed."]);
    exit;
}

$_SESSION['pin_verified_for'] = $student_code;
echo json_encode(["success" => true]);
?>
