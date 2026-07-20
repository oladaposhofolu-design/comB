<?php
// verify_pin.php — checks if the submitted PIN matches the student's PIN
header('Content-Type: application/json');
include 'db.php';

$data = json_decode(file_get_contents("php://input"), true);
$student_code = mysqli_real_escape_string($conn, $data['student_code'] ?? '');
$pin          = mysqli_real_escape_string($conn, $data['pin'] ?? '');

if(!$student_code || !$pin){
    echo json_encode(["success"=>false, "message"=>"Missing data"]);
    exit;
}

$result = mysqli_query($conn,
    "SELECT pin FROM students WHERE student_code='$student_code'");
$student = mysqli_fetch_assoc($result);

if(!$student){
    echo json_encode(["success"=>false, "message"=>"Student not found"]);
    exit;
}

if($student['pin'] === null || $student['pin'] === ''){
    echo json_encode(["success"=>false, "message"=>"No PIN set for this student. Please contact admin."]);
    exit;
}

if($student['pin'] === $pin){
    echo json_encode(["success"=>true, "message"=>"PIN verified"]);
} else {
    echo json_encode(["success"=>false, "message"=>"Incorrect PIN. Please try again."]);
}
?>
