<?php
// ============================================================
// delete_fingerprint.php
// Admin-only endpoint (called from the Manage Students table)
// that removes ALL enrolled WebAuthn credentials for a given
// student — used when a student needs to re-enroll (lost/
// replaced phone, enrolled by mistake, etc.).
// ============================================================
header('Content-Type: application/json');
session_start();
if(!isset($_SESSION['admin'])){
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}
include 'db.php';

$data = json_decode(file_get_contents("php://input"), true);
$student_code = mysqli_real_escape_string($conn, $data['student_code'] ?? '');

if (!$student_code) {
    echo json_encode(["success" => false, "message" => "Student code missing."]);
    exit;
}

$result = mysqli_query($conn, "DELETE FROM webauthn_credentials WHERE student_code='$student_code'");

if ($result) {
    $deletedCount = mysqli_affected_rows($conn);
    echo json_encode([
        "success" => true,
        "message" => "Removed $deletedCount credential(s) for $student_code.",
        "count" => $deletedCount
    ]);
} else {
    echo json_encode(["success" => false, "message" => "Database error: " . mysqli_error($conn)]);
}
?>
