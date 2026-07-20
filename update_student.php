<?php
// ============================================================
// update_student.php
// Admin-only endpoint (called from the Edit Student modal in
// dashboard.php) that updates a student's code, full name, and
// allowed attendance days.
//
// Changing student_code is more than a simple field update: it's
// the key used throughout the system (attendance history, PIN,
// QR codes, and fingerprint credentials). This endpoint keeps
// everything consistent by:
//   1. Validating the new code isn't already used by someone else
//   2. Updating the students table
//   3. Cascading the rename into attendance.student_code, so past
//      attendance records stay linked to the right student
//   4. Cascading the rename into webauthn_credentials.student_code
//      too, so an enrolled fingerprint isn't orphaned
// ============================================================
header('Content-Type: application/json');
session_start();
if(!isset($_SESSION['admin'])){
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}
include 'db.php';

$data = json_decode(file_get_contents("php://input"), true);

$original_code = mysqli_real_escape_string($conn, $data['original_code'] ?? '');
$new_code      = trim($data['student_code'] ?? '');
$full_name     = trim($data['full_name'] ?? '');
$allowed_days  = trim($data['allowed_days'] ?? '');

if (!$original_code) {
    echo json_encode(["success" => false, "message" => "Original student code missing."]);
    exit;
}
if ($new_code === '') {
    echo json_encode(["success" => false, "message" => "Student code cannot be empty."]);
    exit;
}
if ($full_name === '') {
    echo json_encode(["success" => false, "message" => "Full name cannot be empty."]);
    exit;
}
if ($allowed_days === '') {
    echo json_encode(["success" => false, "message" => "Select at least one allowed day."]);
    exit;
}

// Validate each submitted day against the known weekday list
$validDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$submittedDays = array_map('trim', explode(',', $allowed_days));
foreach ($submittedDays as $day) {
    if (!in_array($day, $validDays, true)) {
        echo json_encode(["success" => false, "message" => "Invalid day: $day"]);
        exit;
    }
}

$new_code = strtoupper($new_code);
$new_code_esc      = mysqli_real_escape_string($conn, $new_code);
$full_name_esc     = mysqli_real_escape_string($conn, strtoupper($full_name));
$allowed_days_esc  = mysqli_real_escape_string($conn, implode(',', $submittedDays));

// Confirm the student being edited actually exists
$check = mysqli_query($conn, "SELECT id FROM students WHERE student_code='$original_code'");
if (mysqli_num_rows($check) === 0) {
    echo json_encode(["success" => false, "message" => "Student not found."]);
    exit;
}

$codeChanged = ($new_code !== $original_code);

// If the code is changing, make sure the new one isn't already taken
// by a different student.
if ($codeChanged) {
    $dupe = mysqli_query($conn,
        "SELECT id FROM students WHERE student_code='$new_code_esc' AND student_code != '$original_code'");
    if (mysqli_num_rows($dupe) > 0) {
        echo json_encode(["success" => false, "message" => "Student code \"$new_code\" is already in use by another student."]);
        exit;
    }
}

// ---- Update the students table ----
$sql = "UPDATE students SET student_code='$new_code_esc', full_name='$full_name_esc', allowed_days='$allowed_days_esc' WHERE student_code='$original_code'";

if (!mysqli_query($conn, $sql)) {
    echo json_encode(["success" => false, "message" => "Database error: " . mysqli_error($conn)]);
    exit;
}

// ---- Cascade the code rename so history stays linked ----
if ($codeChanged) {
    // Historical attendance rows reference student_code directly
    // (no foreign key), so update them explicitly.
    mysqli_query($conn,
        "UPDATE attendance SET student_code='$new_code_esc' WHERE student_code='$original_code'");

    // Enrolled fingerprint credentials also reference student_code.
    // Wrapped defensively in case this install doesn't have the
    // fingerprint feature's table yet.
    @mysqli_query($conn,
        "UPDATE webauthn_credentials SET student_code='$new_code_esc' WHERE student_code='$original_code'");
}

echo json_encode([
    "success" => true,
    "message" => "Student updated.",
    "student_code" => $new_code_esc,
    "full_name" => $full_name_esc,
    "allowed_days" => $allowed_days_esc,
    "code_changed" => $codeChanged
]);
?>
