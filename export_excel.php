<?php

session_start();

if(!isset($_SESSION['admin'])){
header("Location: login.php");
}

include 'db.php';

header("Content-Type:
application/vnd.ms-excel");

header("Content-Disposition:
attachment; filename=attendance.xls");

echo "

<table border='1'>

<tr>

<th>Student Code</th>
<th>Total Present</th>
<th>Attendance Percentage</th>

</tr>

";

$query = mysqli_query($conn,

"SELECT
student_code,
COUNT(*) as total

FROM attendance

WHERE status='ALLOWED'

GROUP BY student_code");

while($row=mysqli_fetch_assoc($query)){

$percentage =
($row['total']/20)*100;

echo "

<tr>

<td>{$row['student_code']}</td>

<td>{$row['total']}</td>

<td>".round($percentage)."%</td>

</tr>

";

}

echo "</table>";

?>