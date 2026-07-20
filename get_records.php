<?php
// get_records.php — returns attendance records as JSON
header('Content-Type: application/json');
include 'db.php';

$where = "1=1";

// If specific date is provided, filter by that date only
if(isset($_GET['date']) && $_GET['date'] !== ''){
    $date = mysqli_real_escape_string($conn, $_GET['date']);
    $where = "attendance_date='$date'";
}
// Otherwise filter by year and month
elseif(isset($_GET['year']) && isset($_GET['month'])){
    $year  = intval($_GET['year']);
    $month = intval($_GET['month']);
    $where = "YEAR(attendance_date)=$year AND MONTH(attendance_date)=$month";
}
// Default: show only today
else {
    $where = "attendance_date=CURDATE()";
}

$sql = "SELECT * FROM attendance WHERE $where ORDER BY id DESC";
$result = mysqli_query($conn, $sql);

$records = [];
while($row = mysqli_fetch_assoc($result)){
    $row['attendance_date'] = date('M d, Y', strtotime($row['attendance_date']));
    $row['attendance_time'] = date('h:i:s A', strtotime($row['attendance_time']));
    $records[] = $row;
}

echo json_encode($records);
?>