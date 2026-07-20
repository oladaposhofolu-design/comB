<?php

session_start();

if(!isset($_SESSION['admin'])){
header("Location: login.php");
}

include 'db.php';

$query = mysqli_query($conn,

"SELECT * FROM attendance
ORDER BY id DESC");

?>

<h1>Attendance Records</h1>

<table border="1" cellpadding="10">

<tr>
<th>Student</th>
<th>Status</th>
<th>Day</th>
<th>Date</th>
<th>Time</th>
</tr>

<?php

while($row=mysqli_fetch_assoc($query)){

echo "

<tr>

<td>{$row['student_code']}</td>
<td>{$row['status']}</td>
<td>{$row['attendance_day']}</td>
<td>{$row['attendance_date']}</td>
<td>{$row['attendance_time']}</td>

</tr>

";

}

?>

</table>