<?php
// mark_attendance.php — saves attendance, one record per student per day
header('Content-Type: application/json');
include 'db.php';

$data = json_decode(file_get_contents("php://input"), true);

if(!$data){
    echo json_encode(["success"=>false, "message"=>"No data received"]);
    exit;
}

$student_code = mysqli_real_escape_string($conn, $data['student_code'] ?? '');
$full_name    = mysqli_real_escape_string($conn, $data['full_name']    ?? '');
$allowed_days = mysqli_real_escape_string($conn, $data['allowed_days'] ?? '');
$status       = mysqli_real_escape_string($conn, $data['status']       ?? '');
$day          = mysqli_real_escape_string($conn, $data['day']          ?? '');
$source       = mysqli_real_escape_string($conn, $data['source']       ?? 'qr');

$date = date('Y-m-d');
$time = date('H:i:s');

// ── CHECK: has this student already marked attendance today? ──
$check = mysqli_query($conn,
    "SELECT id FROM attendance
     WHERE full_name='$full_name'
     AND attendance_date='$date'");

if(mysqli_num_rows($check) > 0){
    // Already marked today — block it
    echo json_encode([
        "success"  => false,
        "duplicate"=> true,
        "message"  => "$full_name has already marked attendance today."
    ]);
    exit;
}

// ── INSERT new attendance record ──
$sql = "INSERT INTO attendance
        (student_code, full_name, allowed_days, status,
         attendance_day, attendance_date, attendance_time, source)
        VALUES
        ('$student_code','$full_name','$allowed_days','$status',
         '$day','$date','$time','$source')";

if(mysqli_query($conn, $sql)){
    echo json_encode(["success"=>true, "message"=>"Attendance saved"]);
} else {
    echo json_encode(["success"=>false, "message"=>mysqli_error($conn)]);
}

?>
