<?php
// update_pin.php — admin updates or clears a student's PIN
header('Content-Type: application/json');
session_start();
if(!isset($_SESSION['admin'])){ echo json_encode(["success"=>false,"message"=>"Unauthorized"]); exit; }
include 'db.php';

$data = json_decode(file_get_contents("php://input"), true);
$student_code = mysqli_real_escape_string($conn, $data['student_code'] ?? '');
$pin          = $data['pin'] ?? '';
$action       = $data['action'] ?? 'set'; // 'set' or 'delete'

if(!$student_code){
    echo json_encode(["success"=>false, "message"=>"Student code missing."]);
    exit;
}

if($action === 'delete'){
    mysqli_query($conn,"UPDATE students SET pin=NULL WHERE student_code='$student_code'");
    echo json_encode(["success"=>true, "message"=>"PIN deleted successfully."]);
    exit;
}

// Set PIN
$pin = mysqli_real_escape_string($conn, $pin);
if(strlen($pin) !== 4 || !ctype_digit($pin)){
    echo json_encode(["success"=>false, "message"=>"PIN must be exactly 4 digits."]);
    exit;
}
mysqli_query($conn,"UPDATE students SET pin='$pin' WHERE student_code='$student_code'");
echo json_encode(["success"=>true, "message"=>"PIN updated successfully."]);
?>
