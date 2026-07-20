$currentWeek = "";

while($row = $result->fetch_assoc()) {

    if ($currentWeek != $row['week_number']) {
        $currentWeek = $row['week_number'];
        echo "\nWeek " . $currentWeek . "\n";
    }

    echo $row['student_id'] . "," . $row['attendance_date'] . "\n";
}